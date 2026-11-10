<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StocktakeService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function start(int $warehouseId, string $type, string $date, ?string $notes, int $userId): Stocktake
    {
        return DB::transaction(function () use ($warehouseId, $type, $date, $notes, $userId) {
            $warehouse = Warehouse::query()->whereKey($warehouseId)->where('is_active', true)->firstOrFail();
            $stocktake = Stocktake::create([
                'reference' => 'ST-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'warehouse_id' => $warehouse->id,
                'type' => $type,
                'status' => 'counting',
                'count_date' => $date,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $products = Product::query()
                ->leftJoin('product_warehouse', function ($join) use ($warehouse) {
                    $join->on('product_warehouse.product_id', '=', 'products.id')
                        ->where('product_warehouse.warehouse_id', '=', $warehouse->id);
                })
                ->select([
                    'products.id',
                    'products.cost_price',
                    DB::raw('COALESCE(product_warehouse.quantity, 0) as quantity'),
                    DB::raw('COALESCE(NULLIF(product_warehouse.average_cost, 0), products.cost_price) as average_cost'),
                ])
                ->orderBy('products.id')
                ->get();

            $timestamp = now();
            $rows = $products->map(fn ($product) => [
                'stocktake_id' => $stocktake->id,
                'product_id' => $product->id,
                'system_quantity' => $product->quantity,
                'unit_cost' => $product->average_cost,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('stocktake_items')->insert($chunk);
            }

            return $stocktake->load('warehouse', 'items.product');
        });
    }

    public function saveCounts(Stocktake $stocktake, array $counts): Stocktake
    {
        return DB::transaction(function () use ($stocktake, $counts) {
            $stocktake = Stocktake::query()->whereKey($stocktake->id)->lockForUpdate()->firstOrFail();
            if ($stocktake->status !== 'counting') {
                throw ValidationException::withMessages(['stocktake' => 'يمكن تعديل الكميات أثناء حالة العد فقط.']);
            }

            $items = $stocktake->items()->get()->keyBy('id');
            foreach ($counts as $itemId => $countedQuantity) {
                $item = $items->get((int) $itemId);
                if (! $item || filter_var($countedQuantity, FILTER_VALIDATE_INT) === false || (int) $countedQuantity < 0) {
                    throw ValidationException::withMessages(['counts' => 'إحدى الكميات المعدودة غير صالحة أو لا تتبع هذا الجرد.']);
                }
                $item->update(['counted_quantity' => (int) $countedQuantity]);
            }

            return $stocktake->fresh(['items.product', 'warehouse']);
        });
    }

    public function submit(Stocktake $stocktake): Stocktake
    {
        return DB::transaction(function () use ($stocktake) {
            $stocktake = Stocktake::query()->whereKey($stocktake->id)->lockForUpdate()->firstOrFail();
            if ($stocktake->status !== 'counting' || $stocktake->items()->whereNull('counted_quantity')->exists()) {
                throw ValidationException::withMessages(['stocktake' => 'أدخل الكمية المعدودة لكل الأصناف قبل إرسال الجرد للاعتماد.']);
            }

            $stocktake->update(['status' => 'submitted']);

            return $stocktake->fresh();
        });
    }

    public function approve(Stocktake $stocktake, int $userId): Stocktake
    {
        return DB::transaction(function () use ($stocktake, $userId) {
            $stocktake = Stocktake::query()->whereKey($stocktake->id)->lockForUpdate()->firstOrFail();
            if ($stocktake->status !== 'submitted') {
                throw ValidationException::withMessages(['stocktake' => 'يجب إرسال الجرد كاملاً قبل اعتماده.']);
            }

            $warehouse = Warehouse::query()
                ->whereKey($stocktake->warehouse_id)
                ->where('is_active', true)
                ->firstOrFail();
            $items = $stocktake->items()->orderBy('product_id')->get();
            $movement = StockMovement::create([
                'reference' => 'SM-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'type' => 'stocktake',
                'status' => 'posted',
                'source_warehouse_id' => $warehouse->id,
                'destination_warehouse_id' => $warehouse->id,
                'movement_date' => $stocktake->count_date,
                'reason' => 'تسوية فروقات الجرد '.$stocktake->reference,
                'created_by' => $stocktake->created_by,
                'approved_by' => $userId,
                'posted_at' => now(),
            ]);

            $positiveCents = 0;
            $negativeCents = 0;
            $inventoryAccount = $this->accounting->systemAccount('inventory');
            $surplusAccount = $this->accounting->systemAccount('inventory_surplus');
            $lossAccount = $this->accounting->systemAccount('inventory_adjustment');

            foreach ($items as $item) {
                $variance = (int) $item->counted_quantity - (int) $item->system_quantity;
                if ($variance === 0) {
                    continue;
                }

                $stock = DB::table('product_warehouse')
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();
                $currentQuantity = (int) ($stock->quantity ?? 0);
                $newQuantity = $currentQuantity + $variance;
                if ($newQuantity < 0) {
                    throw ValidationException::withMessages([
                        'stocktake' => "تعذر اعتماد الفرق للمنتج رقم {$item->product_id} لأن الرصيد تحرك بعد أخذ اللقطة؛ راجع العد.",
                    ]);
                }

                $cost = (string) $item->unit_cost;
                $valueCents = abs($variance) * $this->toMinorUnits($cost);
                if ($variance > 0) {
                    $oldAverage = $this->toMinorUnits((string) ($stock->average_cost ?? $cost));
                    $newAverage = $newQuantity === 0
                        ? $oldAverage
                        : intdiv(($currentQuantity * $oldAverage) + (abs($variance) * $this->toMinorUnits($cost)) + intdiv($newQuantity, 2), $newQuantity);
                    DB::table('product_warehouse')->updateOrInsert(
                        ['warehouse_id' => $warehouse->id, 'product_id' => $item->product_id],
                        [
                            'quantity' => $newQuantity,
                            'average_cost' => $this->fromMinorUnits($newAverage),
                            'created_at' => $stock->created_at ?? now(),
                            'updated_at' => now(),
                        ],
                    );
                    $positiveCents += $valueCents;
                    $movement->items()->create([
                        'product_id' => $item->product_id,
                        'quantity' => $variance,
                        'unit_cost' => $cost,
                        'value' => $this->fromMinorUnits($valueCents),
                        'direction' => 'in',
                    ]);
                } else {
                    DB::table('product_warehouse')
                        ->where('id', $stock->id)
                        ->update(['quantity' => $newQuantity, 'updated_at' => now()]);
                    $negativeCents += $valueCents;
                    $movement->items()->create([
                        'product_id' => $item->product_id,
                        'quantity' => abs($variance),
                        'unit_cost' => $cost,
                        'value' => $this->fromMinorUnits($valueCents),
                        'direction' => 'out',
                    ]);
                }
            }

            $journalLines = [];
            if ($positiveCents > 0) {
                $journalLines[] = ['account_id' => $inventoryAccount->id, 'debit' => $this->fromMinorUnits($positiveCents)];
                $journalLines[] = ['account_id' => $surplusAccount->id, 'credit' => $this->fromMinorUnits($positiveCents)];
            }
            if ($negativeCents > 0) {
                $journalLines[] = ['account_id' => $lossAccount->id, 'debit' => $this->fromMinorUnits($negativeCents)];
                $journalLines[] = ['account_id' => $inventoryAccount->id, 'credit' => $this->fromMinorUnits($negativeCents)];
            }
            if ($journalLines !== []) {
                $this->accounting->post(
                    $stocktake->count_date->toDateString(),
                    'ترحيل فروقات الجرد '.$stocktake->reference,
                    $journalLines,
                    Stocktake::class,
                    (int) $stocktake->id,
                    $userId,
                );
            }

            $stocktake->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'stock_movement_id' => $movement->id,
                'approved_at' => now(),
            ]);

            return $stocktake->fresh(['warehouse', 'items.product', 'movement.items']);
        });
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
