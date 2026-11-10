<?php

namespace App\Http\Controllers;

use App\Models\Stocktake;
use App\Models\Warehouse;
use App\Services\StocktakeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StocktakeController extends Controller
{
    public function index(): View
    {
        return view('inventory.stocktakes', [
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'stocktakes' => Stocktake::query()->with(['warehouse', 'creator'])->latest('id')->paginate(20),
        ]);
    }

    public function store(Request $request, StocktakeService $service): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'type' => ['required', 'in:spot,annual'],
            'count_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $stocktake = $service->start(
            (int) $data['warehouse_id'],
            $data['type'],
            $data['count_date'],
            $data['notes'] ?? null,
            (int) $request->user()->id,
        );

        return redirect()->route('stocktakes.edit', $stocktake);
    }

    public function edit(Stocktake $stocktake): View
    {
        $stocktake->load(['warehouse', 'items.product']);

        return view('inventory.stocktake-edit', ['stocktake' => $stocktake]);
    }

    public function saveCounts(Stocktake $stocktake, Request $request, StocktakeService $service): RedirectResponse
    {
        $validated = $request->validate([
            'counts' => ['required', 'array'],
            'counts.*' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'action' => ['nullable', 'in:save,submit'],
        ]);
        $counts = collect($validated['counts'])
            ->filter(fn ($quantity) => $quantity !== null && $quantity !== '')
            ->all();

        $service->saveCounts($stocktake, $counts);
        if (($validated['action'] ?? 'save') === 'submit') {
            $service->submit($stocktake);
        }

        return back()->with('status', ($validated['action'] ?? 'save') === 'submit'
            ? 'تم إرسال الجرد للاعتماد؛ لم تتغير الأرصدة بعد.'
            : 'تم حفظ الكميات المعدودة.');
    }

    public function approve(Stocktake $stocktake, Request $request, StocktakeService $service): RedirectResponse
    {
        $service->approve($stocktake, (int) $request->user()->id);

        return redirect()->route('stocktakes.edit', $stocktake)->with('status', 'تم اعتماد الجرد وترحيل فروق المخزون والقيد المالي.');
    }
}
