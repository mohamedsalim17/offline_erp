<?php

namespace Database\Seeders;

use App\Models\ChartAccount;
use App\Models\SystemSetting;
use App\Models\Treasury;
use App\Services\AccountingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['1000', 'الأصول', 'asset', null, 'assets', true],
            ['1100', 'الأصول المتداولة', 'asset', 'assets', 'current_assets', true],
            ['1110', 'النقدية والخزائن', 'asset', 'current_assets', 'cash', true],
            ['1111', 'النقد بالصندوق', 'asset', 'cash', 'cash_on_hand', false],
            ['1120', 'النقد في المصارف', 'asset', 'cash', 'cash_at_bank', false],
            ['1200', 'المخزون', 'asset', 'current_assets', 'inventory', false],
            ['2000', 'الالتزامات', 'liability', null, 'liabilities', true],
            ['2100', 'الالتزامات المتداولة', 'liability', 'liabilities', 'current_liabilities', true],
            ['2110', 'ذمم الموردين الدائنة', 'liability', 'current_liabilities', 'accounts_payable', false],
            ['3000', 'حقوق الملكية', 'equity', null, 'equity', true],
            ['3100', 'رصيد افتتاحي / تمويل', 'equity', 'equity', 'opening_balance', false],
            ['4000', 'الإيرادات', 'revenue', null, 'revenue', true],
            ['4100', 'إيرادات المبيعات', 'revenue', 'revenue', 'sales_revenue', false],
            ['4200', 'فائض الجرد', 'revenue', 'revenue', 'inventory_surplus', false],
            ['5000', 'المصروفات', 'expense', null, 'expenses', true],
            ['5100', 'تكلفة البضاعة المباعة', 'expense', 'expenses', 'cost_of_goods_sold', false],
            ['5200', 'فروقات المخزون والمصروفات', 'expense', 'expenses', 'inventory_adjustment', false],
            ['5300', 'مصروفات تشغيل عامة', 'expense', 'expenses', 'general_expenses', false],
        ];

        foreach ($accounts as [$code, $name, $type, $parentKey, $systemKey, $isGroup]) {
            $parentId = $parentKey
                ? ChartAccount::query()->where('system_key', $parentKey)->value('id')
                : null;

            ChartAccount::query()->firstOrCreate(
                ['system_key' => $systemKey],
                [
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    'parent_id' => $parentId,
                    'is_group' => $isGroup,
                    'is_active' => true,
                ],
            );
        }

        $cashAccount = ChartAccount::query()->where('system_key', 'cash_on_hand')->firstOrFail();

        Treasury::query()->firstOrCreate(
            ['code' => 'CASH-MAIN'],
            [
                'name' => 'الخزنة الرئيسية',
                'account_id' => $cashAccount->id,
                'currency' => 'IQD',
                'is_active' => true,
            ],
        );
        $defaultTreasury = Treasury::query()->where('code', 'CASH-MAIN')->firstOrFail();
        SystemSetting::query()->firstOrCreate(
            ['key' => 'finance.default_treasury_id'],
            ['value' => (string) $defaultTreasury->id, 'type' => 'integer', 'group' => 'finance', 'is_public' => false],
        );

        if (! SystemSetting::query()->where('key', 'finance.opening_stock_migrated')->exists()) {
            $stockValue = (string) DB::table('product_warehouse')
                ->selectRaw('COALESCE(SUM(quantity * average_cost), 0) as value')
                ->value('value');
            if ((float) $stockValue > 0) {
                app(AccountingService::class)->post(
                    now()->toDateString(),
                    'ترحيل قيمة أرصدة المخزون الموجودة عند تفعيل دفتر الأستاذ',
                    [
                        ['account_id' => ChartAccount::query()->where('system_key', 'inventory')->value('id'), 'debit' => $stockValue],
                        ['account_id' => ChartAccount::query()->where('system_key', 'opening_balance')->value('id'), 'credit' => $stockValue],
                    ],
                    'system.opening-inventory',
                );
            }
            SystemSetting::updateOrCreate(
                ['key' => 'finance.opening_stock_migrated'],
                ['value' => '1', 'type' => 'boolean', 'group' => 'finance', 'is_public' => false],
            );
        }
    }
}
