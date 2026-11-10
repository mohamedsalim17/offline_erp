<?php

namespace App\Http\Controllers;

use App\Models\ChartAccount;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    public function index(): View
    {
        return view('inventory.movements', [
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku', 'unit_name']),
            'accounts' => ChartAccount::query()->where('is_active', true)->where('is_group', false)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'movements' => StockMovement::query()
                ->with(['items.product', 'sourceWarehouse', 'destinationWarehouse', 'creator'])
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function store(Request $request, StockMovementService $service): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['receipt', 'issue', 'transfer'])],
            'source_warehouse_id' => ['nullable', 'required_if:type,issue,transfer', 'integer', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['nullable', 'required_if:type,receipt,transfer', 'integer', 'exists:warehouses,id'],
            'offset_account_id' => ['nullable', 'required_if:type,receipt,issue', 'integer', 'exists:chart_accounts,id'],
            'movement_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['nullable', 'required_if:type,receipt', 'numeric', 'min:0', 'max:999999999'],
        ]);

        $service->create([
            'type' => $data['type'],
            'source_warehouse_id' => $data['source_warehouse_id'] ?? null,
            'destination_warehouse_id' => $data['destination_warehouse_id'] ?? null,
            'offset_account_id' => $data['offset_account_id'] ?? null,
            'movement_date' => $data['movement_date'],
            'reason' => $data['reason'],
            'items' => array_map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'unit_cost' => (string) ($item['unit_cost'] ?? '0'),
            ], $data['items']),
        ], (int) $request->user()->id);

        return back()->with('status', 'تم تسجيل الحركة كطلب بانتظار الاعتماد؛ لم يتغير الرصيد بعد.');
    }

    public function approve(StockMovement $movement, Request $request, StockMovementService $service): RedirectResponse
    {
        $service->approve($movement, (int) $request->user()->id);

        return back()->with('status', 'تم اعتماد الحركة وتحديث الأرصدة وترحيل القيد عند الحاجة.');
    }

    public function reject(StockMovement $movement, Request $request, StockMovementService $service): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $service->reject($movement, (int) $request->user()->id, $validated['reason']);

        return back()->with('status', 'تم رفض طلب الحركة دون تعديل الأرصدة.');
    }
}
