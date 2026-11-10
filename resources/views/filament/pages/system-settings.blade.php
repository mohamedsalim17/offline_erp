<x-filament-panels::page>
    <div class="space-y-6">
        @if ($canManagePosSettings)
            <x-filament::section>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">إعدادات نقاط البيع</h2>
                        <p class="mt-1 text-sm text-gray-500">إعداد الطابعة الحرارية والمخزن الافتراضي وسلوك الإيصال.</p>
                    </div>
                    <x-filament::button tag="a" :href="route('pos.settings')" icon="heroicon-m-shopping-cart" color="gray">
                        فتح إعدادات POS
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif
        @if ($canViewReports)
            <x-filament::section>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">التقارير المتقدمة</h2>
                        <p class="mt-1 text-sm text-gray-500">المنتجات الأعلى مبيعاً وأرصدة المخازن الفرعية مع تصدير Excel وPDF.</p>
                    </div>
                    <x-filament::button tag="a" :href="\App\Filament\Pages\AdvancedReports::getUrl()" icon="heroicon-m-chart-bar-square" color="gray">
                        فتح التقارير
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        @if (session('status'))
            <x-filament::section>
                <p class="text-sm font-medium text-success-600">{{ session('status') }}</p>
            </x-filament::section>
        @endif

        @if ($errors->any())
            <x-filament::section>
                <div class="space-y-1 text-sm font-medium text-danger-600" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @if ($canUpdateSettings)
            <form
                method="POST"
                action="{{ route('system-settings.update') }}"
                enctype="multipart/form-data"
                class="space-y-6"
            >
                @csrf
                @method('PUT')

                <x-filament::section>
                    <x-slot name="heading">اللغة والهوية المؤسسية</x-slot>
                    <x-slot name="description">تظهر هوية الشركة في عنوان لوحة الإدارة والإيصالات.</x-slot>

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="space-y-2 text-sm font-medium">
                            <span>اسم الشركة</span>
                            <input
                                class="fi-input w-full rounded-lg border-gray-300"
                                name="company_name"
                                value="{{ old('company_name', $settings['company_name']) }}"
                                maxlength="120"
                                required
                            >
                        </label>

                        <label class="space-y-2 text-sm font-medium">
                            <span>لغة النظام الافتراضية</span>
                            <select class="fi-input w-full rounded-lg border-gray-300" name="locale" required>
                                <option value="ar" @selected(old('locale', $settings['locale']) === 'ar')>العربية — RTL</option>
                                <option value="en" @selected(old('locale', $settings['locale']) === 'en')>English — LTR</option>
                            </select>
                        </label>

                        <label class="space-y-2 text-sm font-medium">
                            <span>عنوان الشركة</span>
                            <input
                                class="fi-input w-full rounded-lg border-gray-300"
                                name="company_address"
                                value="{{ old('company_address', $settings['company_address']) }}"
                                maxlength="255"
                            >
                        </label>

                        <label class="space-y-2 text-sm font-medium">
                            <span>هاتف الشركة</span>
                            <input
                                class="fi-input w-full rounded-lg border-gray-300"
                                name="company_phone"
                                value="{{ old('company_phone', $settings['company_phone']) }}"
                                maxlength="40"
                                dir="ltr"
                            >
                        </label>

                        <label class="space-y-2 text-sm font-medium">
                            <span>البريد الإلكتروني</span>
                            <input
                                class="fi-input w-full rounded-lg border-gray-300"
                                type="email"
                                name="company_email"
                                value="{{ old('company_email', $settings['company_email']) }}"
                                maxlength="255"
                                dir="ltr"
                            >
                        </label>

                        <label class="space-y-2 text-sm font-medium">
                            <span>شعار الشركة (PNG أو JPG أو WEBP، بحد أقصى 2MB)</span>
                            <input
                                class="fi-input w-full rounded-lg border-gray-300"
                                type="file"
                                name="company_logo"
                                accept="image/png,image/jpeg,image/webp"
                            >
                        </label>
                    </div>

                    @if ($settings['company_logo_path'])
                        <div class="mt-5 flex items-center gap-4">
                            <img
                                src="{{ route('company.logo') }}"
                                alt="شعار الشركة الحالي"
                                class="h-16 max-w-48 rounded border border-gray-200 bg-white object-contain p-2"
                            >
                            <span class="text-sm text-gray-500">الشعار الحالي</span>
                        </div>
                    @endif

                    <div class="mt-6">
                        <x-filament::button type="submit" icon="heroicon-m-check">
                            حفظ إعدادات النظام
                        </x-filament::button>
                    </div>
                </x-filament::section>
            </form>
        @else
            <x-filament::section>
                <x-slot name="heading">اللغة والهوية المؤسسية</x-slot>
                <div class="flex items-center gap-4">
                    @if ($settings['company_logo_path'])
                        <img src="{{ route('company.logo') }}" alt="شعار الشركة" class="h-16 max-w-48 object-contain">
                    @endif
                    <div>
                        <p class="font-semibold">{{ $settings['company_name'] }}</p>
                        <p class="text-sm text-gray-500">{{ $settings['company_address'] }}</p>
                    </div>
                </div>
                <p class="mt-4 text-sm text-gray-500">لا تملك صلاحية تعديل إعدادات النظام.</p>
            </x-filament::section>
        @endif

        @if ($canViewBackups)
            <x-filament::section>
                <x-slot name="heading">النسخ الاحتياطي والاستعادة</x-slot>
                <x-slot name="description">
                    تحفظ النسخ في مجلد خاص محلياً. قبل الاستعادة ينشئ النظام نسخة أمان تلقائية لقاعدة البيانات الحالية.
                </x-slot>

                <div class="mb-6 flex flex-wrap gap-3">
                    @if ($canCreateBackups)
                        <form method="POST" action="{{ route('system-settings.backups.create') }}">
                            @csrf
                            <x-filament::button type="submit" icon="heroicon-m-archive-box-arrow-down">
                                إنشاء نسخة الآن
                            </x-filament::button>
                        </form>
                    @endif

                    @if ($canUploadBackups)
                        <form
                            method="POST"
                            action="{{ route('system-settings.backups.upload') }}"
                            enctype="multipart/form-data"
                            class="flex flex-wrap items-center gap-3"
                        >
                            @csrf
                            <input
                                type="file"
                                name="backup_file"
                                accept=".sql"
                                required
                                class="fi-input rounded-lg border-gray-300"
                            >
                            <x-filament::button type="submit" color="gray" icon="heroicon-m-arrow-up-tray">
                                رفع ملف SQL
                            </x-filament::button>
                        </form>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-start text-gray-600 dark:text-gray-300">
                                <th class="px-3 py-3">النسخة</th>
                                <th class="px-3 py-3">الحالة</th>
                                <th class="px-3 py-3">الحجم</th>
                                <th class="px-3 py-3">تاريخ الإنشاء</th>
                                <th class="px-3 py-3">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse ($backups as $backup)
                                <tr>
                                    <td class="px-3 py-3">
                                        <span class="font-medium">{{ $backup->name }}</span>
                                        @if ($backup->error_message)
                                            <p class="mt-1 text-xs text-danger-600">{{ $backup->error_message }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">{{ $backup->status }}</td>
                                    <td class="px-3 py-3">
                                        {{ $backup->size_bytes ? number_format($backup->size_bytes / 1048576, 2).' MB' : '—' }}
                                    </td>
                                    <td class="px-3 py-3">{{ $backup->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            @if (in_array($backup->status, ['completed', 'uploaded'], true))
                                                <a
                                                    class="text-primary-600 underline"
                                                    href="{{ route('system-settings.backups.download', $backup) }}"
                                                >تنزيل</a>
                                            @endif

                                            @if ($canRestoreBackups && in_array($backup->status, ['completed', 'uploaded'], true))
                                                <form
                                                    method="POST"
                                                    action="{{ route('system-settings.backups.restore', $backup) }}"
                                                    onsubmit="return confirm('سيتم استبدال بيانات قاعدة البيانات الحالية. سيُنشئ النظام نسخة أمان تلقائية أولاً. هل تريد المتابعة؟')"
                                                >
                                                    @csrf
                                                    <input type="hidden" name="confirm_restore" value="1">
                                                    <button class="text-warning-600 underline" type="submit">استعادة</button>
                                                </form>
                                            @endif

                                            @if ($canDeleteBackups)
                                                <form
                                                    method="POST"
                                                    action="{{ route('system-settings.backups.delete', $backup) }}"
                                                    onsubmit="return confirm('هل تريد حذف ملف النسخة نهائياً؟')"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-danger-600 underline" type="submit">حذف</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-3 py-6 text-center text-gray-500" colspan="5">
                                        لا توجد نسخ احتياطية بعد.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
