<x-filament-panels::page>
    <style>
        @media print {
            .fi-sidebar,
            .fi-topbar,
            .fi-header,
            .fi-page-header,
            .reports-filter,
            .fi-section-header-after-ctn {
                display: none !important;
            }

            .fi-main,
            .fi-page,
            .fi-section,
            .fi-section-content {
                padding: 0 !important;
                margin: 0 !important;
                border: 0 !important;
                box-shadow: none !important;
            }

            body {
                background: #fff !important;
            }
        }
    </style>
    @php
        $isTopProducts = $filters['report'] === 'top-products';
        $exportParameters = array_filter($filters, fn ($value) => filled($value));
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <form method="GET" action="{{ \App\Filament\Pages\AdvancedReports::getUrl() }}" class="reports-filter grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <label class="space-y-2 text-sm font-medium">
                    <span>نوع التقرير</span>
                    <select class="fi-input w-full rounded-lg border-gray-300" name="report">
                        <option value="top-products" @selected($isTopProducts)>المنتجات الأعلى مبيعاً</option>
                        <option value="warehouse-balances" @selected(! $isTopProducts)>أرصدة المخازن الفرعية</option>
                    </select>
                </label>

                <label class="space-y-2 text-sm font-medium">
                    <span>{{ $isTopProducts ? 'من تاريخ' : 'المخزن الرئيسي' }}</span>
                    @if ($isTopProducts)
                        <input class="fi-input w-full rounded-lg border-gray-300" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
                    @else
                        <select class="fi-input w-full rounded-lg border-gray-300" name="warehouse_id">
                            <option value="">كل المخازن الرئيسية</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse->id)>
                                    {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </label>

                <label class="space-y-2 text-sm font-medium">
                    <span>{{ $isTopProducts ? 'إلى تاريخ' : 'ملاحظة' }}</span>
                    @if ($isTopProducts)
                        <input class="fi-input w-full rounded-lg border-gray-300" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
                    @else
                        <span class="block pt-2 text-gray-500">يعرض الرصيد الحالي للمخازن الفرعية.</span>
                    @endif
                </label>

                @if ($isTopProducts)
                    <label class="space-y-2 text-sm font-medium">
                        <span>المخزن</span>
                        <select class="fi-input w-full rounded-lg border-gray-300" name="warehouse_id">
                            <option value="">كل المخازن</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse->id)>
                                    {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <div class="flex items-end gap-2">
                    <x-filament::button type="submit" icon="heroicon-m-funnel">
                        تطبيق الفلاتر
                    </x-filament::button>
                    <x-filament::button tag="a" :href="\App\Filament\Pages\AdvancedReports::getUrl()" color="gray">
                        مسح
                    </x-filament::button>
                </div>
            </form>

            <p class="mt-4 text-sm text-gray-500">
                @if ($isTopProducts)
                    التقرير يحتسب المبيعات المكتملة فقط، ويرتب المنتجات حسب الكمية المباعة ثم قيمة المبيعات. اترك التاريخ فارغاً لعرض كامل السجل.
                @else
                    يعرض التقرير الرصيد الحالي للمخازن الفرعية وقيمته بسعر التكلفة وسعر البيع، بما في ذلك الفروع ذات الرصيد الصفري.
                @endif
            </p>
        </x-filament::section>

        @if ($isTopProducts)
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-filament::section>
                    <p class="text-sm text-gray-500">عدد المنتجات المباعة</p>
                    <p class="mt-2 text-2xl font-bold">{{ number_format((int) $summary->products_sold) }}</p>
                </x-filament::section>
                <x-filament::section>
                    <p class="text-sm text-gray-500">إجمالي الوحدات</p>
                    <p class="mt-2 text-2xl font-bold">{{ number_format((int) $summary->units_sold) }}</p>
                </x-filament::section>
                <x-filament::section>
                    <p class="text-sm text-gray-500">إجمالي المبيعات</p>
                    <p class="mt-2 text-2xl font-bold">{{ number_format((float) $summary->gross_sales, 2) }} د.ع</p>
                </x-filament::section>
                <x-filament::section>
                    <p class="text-sm text-gray-500">الربح الإجمالي التقديري</p>
                    <p class="mt-2 text-2xl font-bold">{{ number_format((float) $summary->gross_profit, 2) }} د.ع</p>
                </x-filament::section>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <x-filament::section>
                    <p class="text-sm text-gray-500">المخازن الفرعية</p>
                    <p class="mt-2 text-2xl font-bold">{{ number_format((int) $summary->warehouses_count) }}</p>
                </x-filament::section>
                <x-filament::section>
                    <p class="text-sm text-gray-500">أصناف ذات رصيد</p>
                    <p class="mt-2 text-2xl font-bold">{{ number_format((int) $summary->products_in_stock) }}</p>
                </x-filament::section>
                <x-filament::section>
                    <p class="text-sm text-gray-500">إجمالي الوحدات</p>
                    <p class="mt-2 text-2xl font-bold">{{ number_format((int) $summary->total_units) }}</p>
                </x-filament::section>
                <x-filament::section>
                    <p class="text-sm text-gray-500">قيمة المخزون بالتكلفة</p>
                    <p class="mt-2 text-xl font-bold">{{ number_format((float) $summary->cost_value, 2) }} د.ع</p>
                </x-filament::section>
                <x-filament::section>
                    <p class="text-sm text-gray-500">القيمة بسعر البيع</p>
                    <p class="mt-2 text-xl font-bold">{{ number_format((float) $summary->retail_value, 2) }} د.ع</p>
                </x-filament::section>
            </div>
        @endif

        <x-filament::section>
            <x-slot name="heading">
                {{ $isTopProducts ? 'المنتجات الأعلى مبيعاً' : 'أرصدة المخازن الفرعية' }}
            </x-slot>
            <x-slot name="afterHeader">
                @if ($canExport)
                    <div class="flex flex-wrap gap-2">
                        <a
                            class="fi-btn fi-btn-color-success fi-size-sm inline-flex items-center gap-2 rounded-lg px-3 py-2 font-semibold text-white"
                            href="{{ route('reports.excel', $exportParameters) }}"
                        >تصدير Excel</a>
                        <a
                            class="fi-btn fi-btn-color-danger fi-size-sm inline-flex items-center gap-2 rounded-lg px-3 py-2 font-semibold text-white"
                            href="{{ route('reports.pdf', $exportParameters) }}"
                        >تنزيل PDF</a>
                        <button
                            class="fi-btn fi-btn-color-gray fi-size-sm inline-flex items-center gap-2 rounded-lg px-3 py-2 font-semibold"
                            type="button"
                            onclick="window.print()"
                        >طباعة</button>
                    </div>
                @endif
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        @if ($isTopProducts)
                            <tr class="text-start text-gray-600 dark:text-gray-300">
                                <th class="px-3 py-3">الترتيب</th>
                                <th class="px-3 py-3">المنتج</th>
                                <th class="px-3 py-3">SKU / الباركود</th>
                                <th class="px-3 py-3">الكمية المباعة</th>
                                <th class="px-3 py-3">إجمالي المبيعات</th>
                                <th class="px-3 py-3">التكلفة</th>
                                <th class="px-3 py-3">الربح الإجمالي</th>
                            </tr>
                        @else
                            <tr class="text-start text-gray-600 dark:text-gray-300">
                                <th class="px-3 py-3">المخزن الفرعي</th>
                                <th class="px-3 py-3">المخزن الرئيسي</th>
                                <th class="px-3 py-3">الموقع</th>
                                <th class="px-3 py-3">الأصناف المتوفرة</th>
                                <th class="px-3 py-3">إجمالي الوحدات</th>
                                <th class="px-3 py-3">قيمة التكلفة</th>
                                <th class="px-3 py-3">قيمة البيع</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($rows as $index => $row)
                            @if ($isTopProducts)
                                <tr>
                                    <td class="px-3 py-3">{{ $rows->firstItem() + $index }}</td>
                                    <td class="px-3 py-3 font-medium">{{ $row->product_name }}</td>
                                    <td class="px-3 py-3">
                                        <span dir="ltr">{{ $row->sku }}</span>
                                        <span class="block text-xs text-gray-500" dir="ltr">{{ $row->barcode }}</span>
                                    </td>
                                    <td class="px-3 py-3">{{ number_format((int) $row->units_sold) }}</td>
                                    <td class="px-3 py-3">{{ number_format((float) $row->gross_sales, 2) }} د.ع</td>
                                    <td class="px-3 py-3">{{ number_format((float) $row->cost_total, 2) }} د.ع</td>
                                    <td class="px-3 py-3">{{ number_format((float) $row->gross_profit, 2) }} د.ع</td>
                                </tr>
                            @else
                                <tr>
                                    <td class="px-3 py-3 font-medium">{{ $row->warehouse_name }}</td>
                                    <td class="px-3 py-3">{{ $row->parent_name }}</td>
                                    <td class="px-3 py-3">{{ $row->location ?: '—' }}</td>
                                    <td class="px-3 py-3">{{ number_format((int) $row->products_in_stock) }}</td>
                                    <td class="px-3 py-3">{{ number_format((int) $row->total_units) }}</td>
                                    <td class="px-3 py-3">{{ number_format((float) $row->cost_value, 2) }} د.ع</td>
                                    <td class="px-3 py-3">{{ number_format((float) $row->retail_value, 2) }} د.ع</td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td class="px-3 py-8 text-center text-gray-500" colspan="7">لا توجد بيانات تطابق الفلاتر.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $rows->links() }}
            </div>
        </x-filament::section>

        <p class="text-xs text-gray-500">
            قيم الربح تقديرية: إجمالي قيمة البنود مطروحاً منه تكلفة الوحدة المسجلة وقت البيع، ولا يشمل المصاريف أو المرتجعات.
            تقرير الأرصدة يعرض الرصيد الحالي ويضربه في أسعار المنتج الحالية.
        </p>
    </div>
</x-filament-panels::page>
