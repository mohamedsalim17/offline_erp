<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>إعدادات نقطة البيع | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="pos-shell">
    <header class="pos-header">
        <div>
            <h1>إعدادات نقطة البيع</h1>
            <small>تُحفظ الإعدادات في قاعدة البيانات المحلية</small>
        </div>
        <nav class="pos-header-links">
            <a href="{{ route('pos.index') }}">العودة إلى نقطة البيع</a>
            <a href="{{ url('/admin') }}">لوحة الإدارة</a>
        </nav>
    </header>

    <main class="pos-settings-main">
        <form class="pos-panel pos-settings-form" method="POST" action="{{ route('pos.settings.update') }}">
            @csrf
            @method('PUT')

            @if (session('status'))
                <p class="pos-alert pos-alert-success" role="status">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <div class="pos-alert pos-alert-error" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section>
                <h2>بيانات المتجر على الإيصال</h2>
                <label class="pos-field">
                    اسم المتجر
                    <input name="store_name" value="{{ old('store_name', $settings['store_name']) }}" maxlength="120" required>
                </label>
                <label class="pos-field">
                    العنوان
                    <input name="store_address" value="{{ old('store_address', $settings['store_address']) }}" maxlength="255">
                </label>
                <label class="pos-field">
                    رقم الهاتف
                    <input name="store_phone" value="{{ old('store_phone', $settings['store_phone']) }}" maxlength="40" dir="ltr">
                </label>
                <label class="pos-field">
                    عبارة أسفل الإيصال
                    <input name="receipt_footer" value="{{ old('receipt_footer', $settings['receipt_footer']) }}" maxlength="255">
                </label>
            </section>

            <section>
                <h2>إعدادات الطابعة ونقطة البيع</h2>
                <label class="pos-field">
                    عرض ورق الطابعة الحرارية
                    <select name="paper_width_mm" required>
                        <option value="58" @selected(old('paper_width_mm', $settings['paper_width_mm']) === '58')>58 مم</option>
                        <option value="80" @selected(old('paper_width_mm', $settings['paper_width_mm']) === '80')>80 مم</option>
                    </select>
                </label>
                <label class="pos-field">
                    المخزن الافتراضي
                    <select name="default_warehouse_id">
                        <option value="">أول مخزن نشط</option>
                        @foreach ($warehouses as $warehouse)
                            <option
                                value="{{ $warehouse->id }}"
                                @selected((string) old('default_warehouse_id', $settings['default_warehouse_id']) === (string) $warehouse->id)
                            >
                                {{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="pos-checkbox-field">
                    <input type="checkbox" name="auto_print" value="1" @checked(old('auto_print', $settings['auto_print']) === '1')>
                    فتح نافذة الطباعة تلقائياً بعد إتمام البيع
                </label>
                <p class="pos-help">
                    اختيار الطابعة الفعلية يتم من إعدادات الطباعة في نظام التشغيل أو نافذة المتصفح؛ لا يسمح المتصفح للموقع بتغيير الطابعة الافتراضية.
                </p>
            </section>

            <div class="pos-settings-actions">
                <button class="pos-button" type="submit">حفظ الإعدادات</button>
                <a class="pos-button pos-button-secondary" href="{{ route('pos.index') }}">إلغاء</a>
            </div>
        </form>
    </main>
</body>
</html>
