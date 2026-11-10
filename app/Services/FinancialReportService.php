<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

class FinancialReportService
{
    public function trialBalance(array $filters): array
    {
        $rows = $this->accountBalances(['to' => $filters['to'] ?? now()->toDateString()]);
        $debitTotal = 0;
        $creditTotal = 0;

        foreach ($rows as $row) {
            $net = (int) round(((float) $row->debit_total - (float) $row->credit_total) * 100);
            if ($net >= 0) {
                $row->balance_debit = $net / 100;
                $row->balance_credit = 0;
            } else {
                $row->balance_debit = 0;
                $row->balance_credit = abs($net) / 100;
            }
            $debitTotal += (int) round($row->balance_debit * 100);
            $creditTotal += (int) round($row->balance_credit * 100);
        }

        return [
            'rows' => $rows,
            'debit_total' => $debitTotal / 100,
            'credit_total' => $creditTotal / 100,
        ];
    }

    public function incomeStatement(array $filters): array
    {
        $rows = $this->accountBalances($filters)
            ->whereIn('type', ['revenue', 'expense'])
            ->values();
        $revenueCents = 0;
        $expenseCents = 0;

        foreach ($rows as $row) {
            $debit = (int) round((float) $row->debit_total * 100);
            $credit = (int) round((float) $row->credit_total * 100);
            if ($row->type === 'revenue') {
                $row->amount = ($credit - $debit) / 100;
                $revenueCents += $credit - $debit;
            } else {
                $row->amount = ($debit - $credit) / 100;
                $expenseCents += $debit - $credit;
            }
        }

        return [
            'rows' => $rows,
            'revenue' => $revenueCents / 100,
            'expenses' => $expenseCents / 100,
            'net_profit' => ($revenueCents - $expenseCents) / 100,
        ];
    }

    public function balanceSheet(array $filters): array
    {
        $rows = $this->accountBalances(['to' => $filters['to'] ?? now()->toDateString()])
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->values();
        $assets = 0;
        $liabilities = 0;
        $equity = 0;

        foreach ($rows as $row) {
            $debit = (int) round((float) $row->debit_total * 100);
            $credit = (int) round((float) $row->credit_total * 100);
            $row->balance = in_array($row->type, ['asset'], true)
                ? ($debit - $credit) / 100
                : ($credit - $debit) / 100;
            if ($row->type === 'asset') {
                $assets += $debit - $credit;
            } elseif ($row->type === 'liability') {
                $liabilities += $credit - $debit;
            } else {
                $equity += $credit - $debit;
            }
        }

        $result = $this->incomeStatement(['to' => $filters['to'] ?? now()->toDateString()]);

        return [
            'rows' => $rows,
            'assets' => $assets / 100,
            'liabilities' => $liabilities / 100,
            'equity' => $equity / 100,
            'retained_result' => $result['net_profit'],
            'liabilities_and_equity' => ($liabilities / 100) + ($equity / 100) + $result['net_profit'],
        ];
    }

    public function treasuryBalances(): object
    {
        return DB::table('treasuries as t')
            ->join('chart_accounts as a', 'a.id', '=', 't.account_id')
            ->leftJoin('journal_lines as l', 'l.treasury_id', '=', 't.id')
            ->select(['t.id', 't.code', 't.name', 't.currency', 'a.name as account_name'])
            ->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) as balance')
            ->groupBy('t.id', 't.code', 't.name', 't.currency', 'a.name')
            ->orderBy('t.name')
            ->get();
    }

    public function stockValuation(): Builder
    {
        return DB::table('product_warehouse as stock')
            ->join('warehouses as w', 'w.id', '=', 'stock.warehouse_id')
            ->join('products as p', 'p.id', '=', 'stock.product_id')
            ->leftJoin('warehouses as parent', 'parent.id', '=', 'w.parent_id')
            ->where('stock.quantity', '>', 0)
            ->select([
                'w.id as warehouse_id',
                'w.name as warehouse_name',
                'parent.name as parent_name',
                'p.name as product_name',
                'p.sku',
                'p.unit_name',
                'stock.quantity',
                'stock.average_cost',
            ])
            ->selectRaw('(stock.quantity * stock.average_cost) as stock_value')
            ->orderBy('parent.name')
            ->orderBy('w.name')
            ->orderBy('p.name');
    }

    public function stockValueTotal(): string
    {
        return (string) DB::table('product_warehouse')
            ->selectRaw('COALESCE(SUM(quantity * average_cost), 0) as total')
            ->value('total');
    }

    public function lowStockAlerts(): object
    {
        return DB::table('product_warehouse as stock')
            ->join('warehouses as w', 'w.id', '=', 'stock.warehouse_id')
            ->join('products as p', 'p.id', '=', 'stock.product_id')
            ->where('w.is_active', true)
            ->whereColumn('stock.quantity', '<=', 'p.minimum_stock_alert')
            ->select([
                'w.name as warehouse_name',
                'p.name as product_name',
                'p.sku',
                'p.barcode',
                'p.unit_name',
                'p.minimum_stock_alert',
                'stock.quantity',
            ])
            ->orderBy('stock.quantity')
            ->orderBy('p.name')
            ->limit(500)
            ->get();
    }

    private function accountBalances(array $filters): \Illuminate\Support\Collection
    {
        return DB::table('chart_accounts as a')
            ->leftJoin('journal_lines as l', 'l.account_id', '=', 'a.id')
            ->leftJoin('journal_entries as e', function ($join) use ($filters) {
                $join->on('e.id', '=', 'l.journal_entry_id')
                    ->whereNotNull('e.posted_at')
                    ->when(filled($filters['from'] ?? null), fn ($query) => $query->whereDate('e.entry_date', '>=', $filters['from']))
                    ->when(filled($filters['to'] ?? null), fn ($query) => $query->whereDate('e.entry_date', '<=', $filters['to']));
            })
            ->where('a.is_group', false)
            ->select(['a.id', 'a.code', 'a.name', 'a.type'])
            ->selectRaw('COALESCE(SUM(CASE WHEN e.id IS NOT NULL THEN l.debit ELSE 0 END), 0) as debit_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN e.id IS NOT NULL THEN l.credit ELSE 0 END), 0) as credit_total')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->orderBy('a.code')
            ->get();
    }
}
