<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SystemSetting;
use App\Models\Treasury;
use App\Models\Warehouse;
use App\Services\AccountingService;
use App\Support\Code39Barcode;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosController extends Controller
{
    private const DEFAULT_SETTINGS = [
        'store_name' => 'نظام إدارة المخزون',
        'store_address' => '',
        'store_phone' => '',
        'company_logo_path' => '',
        'receipt_footer' => 'شكراً لتسوقكم',
        'paper_width_mm' => '80',
        'auto_print' => '1',
        'default_warehouse_id' => '',
    ];

    public function index(): View
    {
        $settings = $this->settings();
        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $treasuries = Treasury::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'currency']);
        $configuredTreasuryId = SystemSetting::query()
            ->where('key', 'finance.default_treasury_id')
            ->value('value');
        $defaultTreasuryId = $treasuries->contains(
            fn (Treasury $treasury) => (string) $treasury->id === (string) $configuredTreasuryId,
        ) ? (string) $configuredTreasuryId : (string) ($treasuries->first()?->id ?? '');
        $defaultWarehouseId = (string) $settings['default_warehouse_id'];

        return view('pos.index', [
            'warehouses' => $warehouses,
            'treasuries' => $treasuries,
            'defaultWarehouseId' => $warehouses->contains(
                fn (Warehouse $warehouse) => (string) $warehouse->id === $defaultWarehouseId,
            ) ? $defaultWarehouseId : (string) ($warehouses->first()?->id ?? ''),
            'defaultTreasuryId' => $defaultTreasuryId,
            'canManageSettings' => request()->user()->can('sales.settings'),
        ]);
    }

    public function sales(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $sales = Sale::query()
            ->with(['warehouse:id,name', 'cashier:id,name'])
            ->when(
                ! $request->user()->can('sales.view-all'),
                fn ($query) => $query->where('cashier_id', $request->user()->id),
            )
            ->when(filled($validated['search'] ?? null), function ($query) use ($validated) {
                $search = $validated['search'];
                $query->where(function ($query) use ($search) {
                    $query->where('receipt_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('pos.sales', [
            'sales' => $sales,
            'search' => $validated['search'] ?? '',
        ]);
    }

    public function editSettings(): View
    {
        $settings = $this->settings();

        return view('pos.settings', [
            'settings' => $settings,
            'warehouses' => Warehouse::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:40'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'paper_width_mm' => ['required', 'in:58,80'],
            'auto_print' => ['sometimes', 'boolean'],
            'default_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        if (! empty($validated['default_warehouse_id'])) {
            $isActive = Warehouse::query()
                ->whereKey($validated['default_warehouse_id'])
                ->where('is_active', true)
                ->exists();

            if (! $isActive) {
                throw ValidationException::withMessages([
                    'default_warehouse_id' => 'يجب اختيار مخزن نشط.',
                ]);
            }
        }

        $validated['auto_print'] = (bool) ($validated['auto_print'] ?? false);

        DB::transaction(function () use ($validated) {
            foreach (self::DEFAULT_SETTINGS as $key => $default) {
                SystemSetting::updateOrCreate(
                    ['key' => "pos.{$key}"],
                    [
                        'value' => $key === 'auto_print'
                            ? ($validated[$key] ? '1' : '0')
                            : (string) ($validated[$key] ?? ''),
                        'type' => $key === 'auto_print' ? 'boolean' : 'string',
                        'group' => 'pos',
                        'description' => "إعداد نقطة البيع: {$key}",
                        'is_public' => false,
                    ],
                );
            }
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'تم حفظ إعدادات نقطة البيع.']);
        }

        return redirect()->route('pos.settings')->with('status', 'تم حفظ إعدادات نقطة البيع.');
    }

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $warehouse = Warehouse::query()
            ->whereKey($validated['warehouse_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $query = Product::query()
            ->join('product_warehouse', function ($join) use ($warehouse) {
                $join->on('products.id', '=', 'product_warehouse.product_id')
                    ->where('product_warehouse.warehouse_id', '=', $warehouse->id);
            })
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.barcode',
                'products.selling_price',
                'product_warehouse.quantity as stock',
            ])
            ->where('product_warehouse.quantity', '>', 0);

        if (filled($validated['barcode'] ?? null)) {
            $query->where('products.barcode', $validated['barcode']);
        } elseif (filled($validated['search'] ?? null)) {
            $search = $validated['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%")
                    ->orWhere('products.barcode', 'like', "%{$search}%");
            });
        } else {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $query->orderBy('products.name')
                ->limit(20)
                ->get()
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'price' => (string) $product->selling_price,
                    'stock' => (int) $product->stock,
                ]),
        ]);
    }

    public function checkout(Request $request, AccountingService $accounting): JsonResponse
    {
        $validated = $request->validate([
            'request_key' => ['required', 'uuid'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'payment_method' => ['required', 'in:cash,bank_app,mixed'],
            'cash_amount' => ['nullable', 'required_if:payment_method,mixed', 'numeric', 'min:0', 'max:9999999999', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'bank_amount' => ['nullable', 'required_if:payment_method,mixed', 'numeric', 'min:0', 'max:9999999999', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'treasury_id' => ['nullable', 'integer', 'exists:treasuries,id'],
            'bank_treasury_id' => ['nullable', 'required_if:payment_method,bank_app,mixed', 'integer', 'exists:treasuries,id'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $user = $request->user();
        $existingSale = Sale::query()
            ->where('request_key', $validated['request_key'])
            ->first();

        if ($existingSale) {
            if ($existingSale->cashier_id !== $user->id) {
                abort(409, 'مفتاح العملية مستخدم بالفعل.');
            }

            return response()->json($this->saleResponse($existingSale));
        }

        try {
            $sale = DB::transaction(function () use ($validated, $user, $accounting) {
                $warehouse = Warehouse::query()
                    ->whereKey($validated['warehouse_id'])
                    ->where('is_active', true)
                    ->first();

                if (! $warehouse) {
                    throw ValidationException::withMessages([
                        'warehouse_id' => 'المخزن المحدد غير موجود أو غير نشط.',
                    ]);
                }

                $items = collect($validated['items'])
                    ->keyBy('product_id');
                $productIds = $items->keys()->map(fn ($id) => (int) $id)->sort()->values();

                $products = Product::query()
                    ->whereIn('id', $productIds)
                    ->orderBy('id')
                    ->get()
                    ->keyBy('id');

                $stocks = DB::table('product_warehouse')
                    ->where('warehouse_id', $warehouse->id)
                    ->whereIn('product_id', $productIds)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                $totalCents = 0;
                $costTotalCents = 0;
                $saleLines = [];

                foreach ($items as $productId => $item) {
                    $product = $products->get((int) $productId);
                    $stock = $stocks->get((int) $productId);
                    $quantity = (int) $item['quantity'];

                    if (! $product || ! $stock || $stock->quantity < $quantity) {
                        throw ValidationException::withMessages([
                            'items' => "الكمية المطلوبة غير متوفرة للمنتج رقم {$productId}.",
                        ]);
                    }

                    $unitPriceCents = $this->toMinorUnits((string) $product->selling_price);
                    $averageCost = (float) ($stock->average_cost ?? 0) > 0
                        ? (string) $stock->average_cost
                        : (string) $product->cost_price;
                    $unitCostCents = $this->toMinorUnits($averageCost);
                    $lineTotalCents = $unitPriceCents * $quantity;
                    $totalCents += $lineTotalCents;
                    $costTotalCents += $unitCostCents * $quantity;
                    $saleLines[] = [
                        'product' => $product,
                        'quantity' => $quantity,
                        'unit_price' => $this->fromMinorUnits($unitPriceCents),
                        'unit_cost' => $this->fromMinorUnits($unitCostCents),
                        'line_total' => $this->fromMinorUnits($lineTotalCents),
                    ];
                }

                [$cashAmountCents, $bankAmountCents] = $this->paymentAmounts($validated, $totalCents);
                $cashTreasury = $cashAmountCents > 0
                    ? $this->activeTreasury($validated['treasury_id'] ?? null, 'treasury_id', true)
                    : null;
                $bankTreasury = $bankAmountCents > 0
                    ? $this->activeTreasury($validated['bank_treasury_id'] ?? null, 'bank_treasury_id')
                    : null;

                if ($cashTreasury && $bankTreasury && $cashTreasury->id === $bankTreasury->id) {
                    throw ValidationException::withMessages([
                        'bank_treasury_id' => 'اختر خزنتين مختلفتين عند تقسيم الدفع بين النقد والتطبيق البنكي.',
                    ]);
                }

                $sale = Sale::create([
                    'request_key' => $validated['request_key'],
                    'receipt_number' => 'TMP-'.Str::upper(Str::random(24)),
                    'cashier_id' => $user->id,
                    'warehouse_id' => $warehouse->id,
                    'cash_treasury_id' => $cashTreasury?->id,
                    'bank_treasury_id' => $bankTreasury?->id,
                    'customer_name' => filled($validated['customer_name'] ?? null) ? trim($validated['customer_name']) : null,
                    'customer_phone' => filled($validated['customer_phone'] ?? null) ? trim($validated['customer_phone']) : null,
                    'status' => 'completed',
                    'payment_method' => $validated['payment_method'],
                    'cash_amount' => $this->fromMinorUnits($cashAmountCents),
                    'bank_amount' => $this->fromMinorUnits($bankAmountCents),
                    'subtotal' => $this->fromMinorUnits($totalCents),
                    'total' => $this->fromMinorUnits($totalCents),
                    'currency' => 'IQD',
                ]);
                $sale->update([
                    'receipt_number' => str_pad((string) $sale->id, 10, '0', STR_PAD_LEFT),
                ]);

                foreach ($saleLines as $line) {
                    $product = $line['product'];
                    $sale->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'barcode' => $product->barcode,
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'unit_cost' => $line['unit_cost'],
                        'line_total' => $line['line_total'],
                    ]);

                    DB::table('product_warehouse')
                        ->where('warehouse_id', $warehouse->id)
                        ->where('product_id', $product->id)
                        ->decrement('quantity', $line['quantity'], ['updated_at' => now()]);
                }

                $journalLines = [];
                if ($cashAmountCents > 0) {
                    $journalLines[] = [
                        'account_id' => $cashTreasury->account_id,
                        'treasury_id' => $cashTreasury->id,
                        'debit' => $this->fromMinorUnits($cashAmountCents),
                    ];
                }
                if ($bankAmountCents > 0) {
                    $journalLines[] = [
                        'account_id' => $bankTreasury->account_id,
                        'treasury_id' => $bankTreasury->id,
                        'debit' => $this->fromMinorUnits($bankAmountCents),
                    ];
                }
                if ($totalCents > 0) {
                    $journalLines[] = [
                        'account_id' => $accounting->systemAccount('sales_revenue')->id,
                        'credit' => $this->fromMinorUnits($totalCents),
                    ];
                }
                if ($costTotalCents > 0) {
                    $journalLines[] = [
                        'account_id' => $accounting->systemAccount('cost_of_goods_sold')->id,
                        'debit' => $this->fromMinorUnits($costTotalCents),
                    ];
                    $journalLines[] = [
                        'account_id' => $accounting->systemAccount('inventory')->id,
                        'credit' => $this->fromMinorUnits($costTotalCents),
                    ];
                }
                if ($journalLines !== []) {
                    $entry = $accounting->post(
                        now()->toDateString(),
                        'بيع نقطة البيع '.$sale->receipt_number,
                        $journalLines,
                        Sale::class,
                        (int) $sale->id,
                        (int) $user->id,
                    );
                    foreach ([
                        ['treasury' => $cashTreasury, 'amount' => $cashAmountCents, 'suffix' => 'CASH', 'label' => 'نقداً'],
                        ['treasury' => $bankTreasury, 'amount' => $bankAmountCents, 'suffix' => 'BANK', 'label' => 'عبر التطبيق البنكي'],
                    ] as $payment) {
                        if ($payment['amount'] === 0) {
                            continue;
                        }

                        \App\Models\TreasuryTransaction::create([
                            'reference' => 'SALE-'.$sale->receipt_number.'-'.$payment['suffix'],
                            'treasury_id' => $payment['treasury']->id,
                            'journal_entry_id' => $entry->id,
                            'type' => 'cash_in',
                            'amount' => $this->fromMinorUnits($payment['amount']),
                            'transaction_date' => now()->toDateString(),
                            'description' => 'تحصيل بيع '.$sale->receipt_number.' '.$payment['label'],
                            'created_by' => $user->id,
                        ]);
                    }
                }

                return $sale;
            });
        } catch (QueryException $exception) {
            $existingSale = Sale::query()
                ->where('request_key', $validated['request_key'])
                ->first();

            if (! $existingSale || $existingSale->cashier_id !== $user->id) {
                throw $exception;
            }

            $sale = $existingSale;
        }

        return response()->json($this->saleResponse($sale), 201);
    }

    public function receipt(Request $request, Sale $sale): View
    {
        abort_unless($sale->cashier_id === $request->user()->id || $request->user()->can('sales.view-all'), 403);

        return $this->invoice($sale, 'customer');
    }

    public function warehouseInvoice(Request $request, Sale $sale): View
    {
        abort_unless($sale->cashier_id === $request->user()->id || $request->user()->can('sales.view-all'), 403);

        return $this->invoice($sale, 'warehouse');
    }

    private function invoice(Sale $sale, string $invoiceType): View
    {
        $sale->load(['items', 'cashier', 'warehouse', 'cashTreasury', 'bankTreasury']);

        $settings = $this->settings();
        $barcodeValue = $sale->receipt_number;

        return view('pos.receipt', [
            'sale' => $sale,
            'settings' => $settings,
            'invoiceType' => $invoiceType,
            'invoiceBarcodeValue' => $barcodeValue,
            'invoiceBarcodeSvg' => app(Code39Barcode::class)->svg($barcodeValue),
        ]);
    }

    private function settings(): array
    {
        $savedSettings = SystemSetting::query()
            ->where('group', 'pos')
            ->pluck('value', 'key')
            ->all();

        $settings = self::DEFAULT_SETTINGS;
        $settings['store_name'] = SystemSetting::query()
            ->where('key', 'company.name')
            ->value('value') ?? $settings['store_name'];
        $settings['store_address'] = SystemSetting::query()
            ->where('key', 'company.address')
            ->value('value') ?? $settings['store_address'];
        $settings['store_phone'] = SystemSetting::query()
            ->where('key', 'company.phone')
            ->value('value') ?? $settings['store_phone'];
        $settings['company_logo_path'] = SystemSetting::query()
            ->where('key', 'company.logo_path')
            ->value('value') ?? '';

        foreach ($settings as $key => $default) {
            $settings[$key] = $savedSettings["pos.{$key}"] ?? $default;
        }

        $settings['company_name'] = SystemSetting::query()
            ->where('key', 'company.name')
            ->value('value') ?: $settings['store_name'];

        return $settings;
    }

    private function saleResponse(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'receipt_number' => $sale->receipt_number,
            'total' => (string) $sale->total,
            'receipt_url' => route('pos.receipt', $sale),
            'warehouse_invoice_url' => route('pos.warehouse-invoice', $sale),
        ];
    }

    private function paymentAmounts(array $validated, int $totalCents): array
    {
        $method = $validated['payment_method'];
        $cashCents = filled($validated['cash_amount'] ?? null)
            ? $this->toMinorUnits((string) $validated['cash_amount'])
            : ($method === 'cash' ? $totalCents : 0);
        $bankCents = filled($validated['bank_amount'] ?? null)
            ? $this->toMinorUnits((string) $validated['bank_amount'])
            : ($method === 'bank_app' ? $totalCents : 0);

        if (
            $cashCents + $bankCents !== $totalCents
            || ($method === 'cash' && ($cashCents !== $totalCents || $bankCents !== 0))
            || ($method === 'bank_app' && ($bankCents !== $totalCents || $cashCents !== 0))
            || ($method === 'mixed' && ($cashCents < 1 || $bankCents < 1))
        ) {
            throw ValidationException::withMessages([
                'payment_method' => 'يجب أن يساوي مجموع النقد والتطبيق البنكي إجمالي الفاتورة، وأن يتطابق مع طريقة الدفع.',
            ]);
        }

        return [$cashCents, $bankCents];
    }

    private function activeTreasury(?int $treasuryId, string $field, bool $allowDefault = false): Treasury
    {
        if ($allowDefault && ! $treasuryId) {
            $treasuryId = (int) SystemSetting::query()
                ->where('key', 'finance.default_treasury_id')
                ->value('value');
        }

        if (! $allowDefault && ! $treasuryId) {
            throw ValidationException::withMessages([
                $field => 'اختر حساب الإيداع البنكي.',
            ]);
        }

        $treasury = Treasury::query()
            ->when($treasuryId, fn ($query) => $query->whereKey($treasuryId))
            ->where('is_active', true)
            ->when(! $treasuryId && $allowDefault, fn ($query) => $query->orderBy('id'))
            ->lockForUpdate()
            ->first();

        if (! $treasury) {
            throw ValidationException::withMessages([
                $field => 'اختر خزنة نشطة لطريقة الدفع أو أنشئ خزنة من صفحة الخزائن.',
            ]);
        }

        return $treasury;
    }

    private function toMinorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function fromMinorUnits(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
