<?php

namespace App\Services;

use App\Models\ChartAccount;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function create(array $data, int $userId): StockMovement
    {
        return DB::transaction(function () use ($data, $userId) {
            $movement = StockMovement::create([
                'reference' => $this->reference(),
                'type' => $data['type'],
                'status' => 'pending',
                'source_warehouse_id' => $data['source_warehouse_id'] ?? null,
                'destination_warehouse_id' => $data['destination_warehouse_id'] ?? null,
                'movement_date' => $data['movement_date'],
                'reason' => $data['reason'],
                'offset_account_id' => $data['offset_account_id'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($data['items'] as $item) {
                $movement->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'direction' => $data['type'] === 'issue' ? 'out' : 'in',
                    'unit_cost' => $data['type'] === 'receipt' ? $item['unit_cost'] : 0,
                ]);
            }

            return $movement->load('items.product', 'sourceWarehouse', 'destinationWarehouse');
        });
    }

    public function approve(StockMovement $movement, int $userId): StockMovement
    {
        return DB::transaction(function () use ($movement, $userId) {
            $movement = StockMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            if ($movement->status !== 'pending') {
                throw ValidationException::withMessages(['movement' => 'هذه الحركة ليست بانتظار الاعتماد.']);
            }

            $movement->load('items');
            $source = $movement->source_warehouse_id
                ? Warehouse::query()->whereKey($movement->source_warehouse_id)->where('is_active', true)->first()
                : null;
            $destination = $movement->destination_warehouse_id
                ? Warehouse::query()->whereKey($movement->destination_warehouse_id)->where('is_active', true)->first()
                : null;

            if (($movement->type === 'receipt' && ! $destination) ||
                ($movement->type === 'issue' && ! $source) ||
                ($movement->type === 'transfer' && (! $source || ! $destination || $source->is($destination)))) {
                throw ValidationException::withMessages(['warehouse_id' => 'المخزن المصدر أو الوجهة غير صالح أو غير نشط.']);
            }

            $productIds = $movement->items->pluck('product_id')->sort()->values();
            $stockRows = DB::table('product_warehouse')
                ->whereIn('warehouse_id', array_filter([$source?->id, $destination?->id]))
                ->whereIn('product_id', $productIds)
                ->orderBy('warehouse_id')
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (object $row) => "{$row->warehouse_id}:{$row->product_id}");

            $inventoryAccount = $this->accounting->systemAccount('inventory');
            $offsetAccount = $movement->offset_account_id
                ? ChartAccount::query()->whereKey($movement->offset_account_id)->where('is_active', true)->where('is_group', false)->first()
                : null;

            if (in_array($movement->type, ['receipt', 'issue'], true) && ! $offsetAccount) {
                throw ValidationException::withMessages(['offset_account_id' => 'اختر حساب المقابل المالي للحركة.']);
            }
            if ($movement->type === 'issue' && $offsetAccount->type !== 'expense') {
                throw ValidationException::withMessages(['offset_account_id' => 'يجب أن يكون حساب الصرف من نوع المصروفات.']);
            }
            if ($movement->type === 'receipt' &&
                $offsetAccount->parent_id === ChartAccount::query()->where('system_key', 'cash')->value('id')) {
                throw ValidationException::withMessages([
                    'offset_account_id' => 'رحّل الاستلام إلى ذمم الموردين أو حساب مناسب، ثم سجّل دفع الخزنة كحركة مستقلة.',
                ]);
            }

            $totalValueCents = 0;
            foreach ($movement->items as $item) {
                $quantity = (int) $item->quantity;
                $sourceKey = $source ? "{$source->id}:{$item->product_id}" : null;
                $destinationKey = $destination ? "{$destination->id}:{$item->product_id}" : null;
                $sourceStock = $sourceKey ? $stockRows->get($sourceKey) : null;
                $destinationStock = $destinationKey ? $stockRows->get($destinationKey) : null;

                if (in_array($movement->type, ['issue', 'transfer'], true) &&
                    (! $sourceStock || (int) $sourceStock->quantity < $quantity)) {
                    throw ValidationException::withMessages([
                        'items' => "الرصيد غير كافٍ للمنتج رقم {$item->product_id} في المخزن المصدر.",
                    ]);
                }

                if ($movement->type === 'receipt') {
                    $cost = (string) $item->unit_cost;
                    $valueCents = $this->toMinorUnits($cost) * $quantity;
                    $this->addStock($destinationStock, (int) $destination->id, (int) $item->product_id, $quantity, $cost);
                } elseif ($movement->type === 'issue') {
                    $cost = (string) $sourceStock->average_cost;
                    $valueCents = $this->toMinorUnits($cost) * $quantity;
                    $this->subtractStock($sourceStock, $quantity);
                } else {
                    $cost = (string) $sourceStock->average_cost;
                    $valueCents = $this->toMinorUnits($cost) * $quantity;
                    $this->subtractStock($sourceStock, $quantity);
                    $this->addStock($destinationStock, (int) $destination->id, (int) $item->product_id, $quantity, $cost);
                }

                $item->update([
                    'unit_cost' => $cost,
                    'value' => $this->fromMinorUnits($valueCents),
                ]);
                $totalValueCents += $valueCents;
            }

            if ($totalValueCents > 0 && in_array($movement->type, ['receipt', 'issue'], true)) {
                $value = $this->fromMinorUnits($totalValueCents);
                $lines = $movement->type === 'receipt'
                    ? [
                        ['account_id' => $inventoryAccount->id, 'debit' => $value],
                        ['account_id' => $offsetAccount->id, 'credit' => $value],
                    ]
                    : [
                        ['account_id' => $offsetAccount->id, 'debit' => $value],
                        ['account_id' => $inventoryAccount->id, 'credit' => $value],
                    ];
                $this->accounting->post(
                    $movement->movement_date->toDateString(),
                    $movement->reason,
                    $lines,
                    StockMovement::class,
                    (int) $movement->id,
                    $userId,
                );
            }

            $movement->update([
                'status' => 'posted',
                'approved_by' => $userId,
                'posted_at' => now(),
            ]);

            return $movement->fresh(['items.product', 'sourceWarehouse', 'destinationWarehouse']);
        });
    }

    public function reject(StockMovement $movement, int $userId, string $reason): StockMovement
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'سبب الرفض مطلوب.']);
        }

        return DB::transaction(function () use ($movement, $userId, $reason) {
            $movement = StockMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            if ($movement->status !== 'pending') {
                throw ValidationException::withMessages(['movement' => 'يمكن رفض الطلبات المعلقة فقط.']);
            }
            $movement->update([
                'status' => 'rejected',
                'approved_by' => $userId,
                'reason' => mb_substr($movement->reason.' — مرفوض: '.$reason, 0, 255),
            ]);

            return $movement;
        });
    }

    private function addStock(?object $stock, int $warehouseId, int $productId, int $quantity, string $unitCost): void
    {
        $oldQuantity = (int) ($stock->quantity ?? 0);
        $oldCostCents = $this->toMinorUnits((string) ($stock->average_cost ?? $unitCost));
        $newQuantity = $oldQuantity + $quantity;
        $incomingCostCents = $this->toMinorUnits($unitCost);
        $averageCostCents = intdiv(($oldQuantity * $oldCostCents) + ($quantity * $incomingCostCents) + intdiv($newQuantity, 2), $newQuantity);
        $now = now();

        DB::table('product_warehouse')->updateOrInsert(
            ['warehouse_id' => $warehouseId, 'product_id' => $productId],
            [
                'quantity' => $newQuantity,
                'average_cost' => $this->fromMinorUnits($averageCostCents),
                'updated_at' => $now,
                'created_at' => $stock->created_at ?? $now,
            ],
        );
    }

    private function subtractStock(object $stock, int $quantity): void
    {
        DB::table('product_warehouse')
            ->where('id', $stock->id)
            ->update([
                'quantity' => (int) $stock->quantity - $quantity,
                'updated_at' => now(),
            ]);
    }

    private function toMinorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0', STR_PAD_LEFT);
    }

    private function fromMinorUnits(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }

    private function reference(): string
    {
        return 'SM-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }
}
