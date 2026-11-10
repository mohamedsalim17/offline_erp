<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## نقطة البيع المحلية (POS)

تعمل نقطة البيع من `/pos` باستخدام Laravel وBlade وAlpine.js. تُضمّن Alpine.js وملفات CSS/JavaScript في ملفات Vite محلية؛ لا توجد مكتبات CDN أو اتصالات بخدمات خارجية. بعد تجهيز اعتمادات المشروع وبناء الواجهة، يعمل التطبيق في الشبكة المحلية أو على الجهاز نفسه دون اتصال بالإنترنت.

### التجهيز والتشغيل دون إنترنت

1. تأكد من تشغيل MySQL المحلي وأن إعدادات قاعدة البيانات في `.env` تشير إلى قاعدة البيانات المحلية الصحيحة.
2. ابنِ أصول الواجهة محلياً مرة واحدة بعد تثبيت اعتمادات Node.js:

   ```bash
   npm run build
   ```

   يستخدم الخادم ملفات `public/build` الناتجة، ولا يحتاج إلى تشغيل Vite dev server أثناء الاستخدام.
3. أنشئ جداول المبيعات والصلاحيات وحدّث بيانات الأدوار:

   ```bash
   php artisan migrate
   php artisan db:seed --class=RolesAndPermissionsSeeder
   ```

4. امنح حساب الكاشير دوراً يسمح باستخدام نقطة البيع. مثلاً بعد استبدال البريد بعنوان الحساب:

   ```bash
   php artisan tinker
   ```

   ثم داخل Tinker:

   ```php
   App\Models\User::where('email', 'cashier@example.com')->firstOrFail()->assignRole('manager');
   ```

5. شغّل الخادم على الجهاز المحلي فقط:

   ```bash
   php artisan serve --host=127.0.0.1 --port=8000
   ```

   ثم افتح `http://127.0.0.1:8000/admin` وسجّل الدخول، أو افتح `http://127.0.0.1:8000/pos`. العنوان `127.0.0.1` يمنع إتاحة الخادم لأجهزة الشبكة.

إذا كانت البيئة مجهزة مسبقاً، فلا حاجة إلى إنترنت عند تشغيل `php artisan migrate` أو الخادم. يجب أن يكون MySQL المحلي عاملاً. لا تشغّل `migrate:fresh` على قاعدة بيانات تحتوي بيانات تريد الاحتفاظ بها.

### استخدام نقطة البيع

- اختر المخزن قبل إضافة المنتجات؛ السلة مرتبطة بالمخزن وتبقى محفوظة في تخزين المتصفح المحلي عند تحديث الصفحة.
- ضع المؤشر في حقل الباركود. تعمل الماسحات المعتادة بوضع لوحة المفاتيح: ترسل الرقم ثم Enter، فيُضاف المنتج مباشرة. يمكن أيضاً البحث بالاسم أو الرمز.
- عدّل الكمية أو احذف المنتج من السلة، ثم اختر طريقة الدفع واضغط «إتمام البيع وطباعة الإيصال».
- يتحقق الخادم من الأسعار والمخزون الحالي، ويحفظ البيع وبنوده ويخصم المخزون في معاملة واحدة. مفتاح العملية يمنع تكرار البيع عند إعادة إرسال الطلب بسبب انقطاع محلي أو ضغط مزدوج.
- بعد البيع تُنشأ فاتورة للعميل ونسخة مخزن، ويظهر في كل نسخة نوعها ورقمها وباركودها واسم الشركة وشعارها والمخزن والكاشير وتفاصيل الأصناف (باركود الصنف، سعر الوحدة، الكمية والإجمالي). باركود الفاتورة من نوع Code 39 ويُولّد محلياً دون مكتبات أو خدمات إنترنت.
- يمكن تسجيل الدفع نقداً، عبر تطبيق بنكي، أو تقسيمه بينهما. عند الدفع المختلط يجب أن يساوي مجموع المبلغين إجمالي الفاتورة، ويُسجل كل مبلغ في خزنة/حساب مستقل. أنشئ حساباً وخزنة بنكية من الإدارة واخترها كحساب إيداع للتطبيق البنكي.
- تُطبع نسختا العميل والمخزن من المتصفح على الطابعة المحلية. اضبط حجم الورق والهوامش في تفضيلات الطابعة بحسب موديلها.

تتوفر إعدادات المتجر والطباعة من «إعدادات نقطة البيع» أو `/pos/settings` للحسابات التي لديها صلاحية `sales.settings`. تشمل اسم المتجر والعنوان والهاتف وعبارة الإيصال، وعرض الورق 58/80 مم، والطباعة التلقائية، والمخزن الافتراضي. اختيار الطابعة الفعلية يبقى من إعدادات نظام التشغيل أو نافذة الطباعة، لأن المتصفح لا يسمح للموقع بتغييرها.

### إعدادات النظام والنسخ الاحتياطي

تظهر صفحة **إعدادات النظام** في قائمة Filament على `/admin/system-settings` للحسابات التي لديها `system-settings.view`. يمكن للمخوّل بـ `system-settings.update` تغيير اللغة الافتراضية العربية/الإنجليزية واسم الشركة وعنوانها وهاتفها وبريدها ورفع شعار PNG/JPG/WEBP (حتى 2MB). يُخزّن الشعار والنسخ في التخزين المحلي الخاص، ويظهر شعار الشركة واسمها في لوحة الإدارة والإيصالات.

تتطلب عمليات نسخ/استعادة قاعدة MySQL توفر `mysqldump.exe` و`mysql.exe` محلياً. يكتشف التطبيق أدوات XAMPP من `..\..\mysql\bin` نسبة إلى مجلد المشروع؛ إن كان مسارهما مختلفاً، اضبط `database.backup_mysqldump_path` و`database.backup_mysql_path` في إعداد Laravel. ينشئ النظام ملفات SQL في `storage/app/private/backups`، ويسجل الحجم وبصمة SHA-256، ويتحقق من البصمة قبل الاستعادة. يمكن رفع ملف `.sql` (بحد أقصى 512MB) ثم تنزيله أو استعادته. **الاستعادة تستبدل بيانات قاعدة البيانات**؛ لذلك يتطلب الإجراء تأكيداً وينشئ نسخة أمان محلية قبل التنفيذ. امنح صلاحيات `backups.create`, `backups.upload`, `backups.restore`, و`backups.delete` للأدوار المناسبة فقط، واحفظ نسخة إضافية خارج الجهاز تحسباً لعطل القرص.

تُعرّف مسارات الأدوات الاختيارية عبر `DB_DUMP_PATH` و`DB_CLIENT_PATH` في `.env`؛ اتركهما فارغين لاستخدام اكتشاف XAMPP التلقائي. بعد التحديث، نفّذ:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

ثم امنح حساب مدير النظام دور `admin` أو `super-admin` من إدارة المستخدمين، أو عبر Tinker:

```php
App\Models\User::where('email', 'admin@example.com')->firstOrFail()->assignRole('admin');
```

لا يمنح الـSeeder الأدوار تلقائياً للمستخدمين الموجودين، تجنباً لتوسيع الصلاحيات دون موافقة.

## التقارير المتقدمة

تظهر صفحة **التقارير المتقدمة** في لوحة Filament على `/admin/advanced-reports` للمستخدم الذي يملك `reports.view`. وتصدير Excel/PDF يتطلب `reports.export`. بعد إضافة الصلاحيات إلى الـSeeder، حدّث أدوار قاعدة البيانات الحالية بأمان:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### المنتجات الأعلى مبيعاً

يجمع التقرير بنود المبيعات المكتملة (`sales.status = completed`) حسب المنتج، ويدعم فترة من/إلى والمخزن. يعرض عدد الوحدات، إجمالي قيمة البنود، التكلفة المحفوظة وقت البيع، والربح الإجمالي التقديري:

```sql
SELECT
    si.product_id,
    si.product_name,
    si.sku,
    si.barcode,
    SUM(si.quantity) AS units_sold,
    SUM(si.line_total) AS gross_sales,
    SUM(si.quantity * si.unit_cost) AS cost_total,
    SUM(si.line_total - si.quantity * si.unit_cost) AS gross_profit
FROM sale_items AS si
JOIN sales AS s ON s.id = si.sale_id
WHERE s.status = 'completed'
  AND (:from_datetime IS NULL OR s.created_at >= :from_datetime)
  AND (:to_datetime IS NULL OR s.created_at <= :to_datetime)
  AND (:warehouse_id IS NULL OR s.warehouse_id = :warehouse_id)
GROUP BY si.product_id, si.product_name, si.sku, si.barcode
ORDER BY units_sold DESC, gross_sales DESC;
```

### أرصدة المخازن الفرعية

يعرض أرصدة المخزون الحالية بحسب كل مخزن فرعي ومخزنه الأب، بما في ذلك الفروع ذات الرصيد الصفري. قيمة التكلفة والبيع هي قيمة الرصيد الحالي مضروبة بأسعار المنتج الحالية:

```sql
SELECT
    child.id AS warehouse_id,
    child.name AS warehouse_name,
    parent.name AS parent_name,
    COUNT(DISTINCT CASE WHEN COALESCE(stock.quantity, 0) > 0 THEN stock.product_id END) AS products_in_stock,
    COALESCE(SUM(stock.quantity), 0) AS total_units,
    COALESCE(SUM(stock.quantity * products.cost_price), 0) AS cost_value,
    COALESCE(SUM(stock.quantity * products.selling_price), 0) AS retail_value
FROM warehouses AS child
JOIN warehouses AS parent ON parent.id = child.parent_id
LEFT JOIN product_warehouse AS stock ON stock.warehouse_id = child.id
LEFT JOIN products ON products.id = stock.product_id
WHERE child.parent_id IS NOT NULL
  AND (:parent_warehouse_id IS NULL OR child.parent_id = :parent_warehouse_id)
GROUP BY child.id, child.name, parent.name
ORDER BY parent.name, child.name;
```

توفر الصفحة ملخصات إجمالية وصفحات نتائج، مع أزرار طباعة مباشرة وتنزيل XLSX من Laravel Excel وتنزيل PDF بواسطة DomPDF. تستخدم ملفات Excel استعلاماً قابلاً للتجزئة إلى أجزاء، بينما يحافظ PDF على تنسيق عربي محلي ويُرفض صراحة إذا تجاوز 5000 صف؛ في هذه الحالة استخدم XLSX أو ضيّق الفترة. لا تعتمد عمليات العرض أو التصدير بعد تثبيت Composer على CDN أو الإنترنت. حزم Composer اللازمة هي `maatwebsite/excel` و`barryvdh/laravel-dompdf`.

الربح في تقرير المبيعات قبل المصاريف والمرتجعات والخصومات/الضرائب (غير المسجلة حالياً). أرصدة المخازن تعرض الحالة الحالية لا لقطة تاريخية.

السلة غير المرسلة محفوظة في المتصفح فقط؛ لا تمسح بيانات الموقع أو تخزين المتصفح قبل إتمام بيع مهم. يحتاج النظام إلى اتصال بين المتصفح وخادم Laravel وMySQL على الجهاز المحلي، لكنه لا يحتاج إلى اتصال بالإنترنت.

## الخزائن والمحاسبة والمخازن والجرد

تعمل الوظائف محلياً على Laravel وMySQL دون CDN أو خدمات خارجية. بعد تحديث الملفات، خذ نسخة احتياطية من قاعدة البيانات ثم نفّذ:

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
npm run build
```

لا تستخدم `migrate:fresh` على قاعدة بيانات عمل. يضيف الترحيل جداول دفتر الأستاذ والخزائن والجرد، ويحوّل حذف المخزن الأب إلى حذف مقيّد لحماية فروعه وأرصدته. ينشئ الـSeeder شجرة الحسابات الأساسية والخزنة الرئيسية والصلاحيات. إذا كانت هناك أرصدة مخزون موجودة قبل التحديث، يرحّل الـSeeder قيمتها الافتتاحية إلى حساب المخزون مقابل حقوق الملكية **مرة واحدة فقط**؛ لا يحذفها أو يعيد ترحيلها عند تكرار الـSeeder.

### الخزائن ودفتر الأستاذ

- افتح **الخزائن والحركات المالية** من لوحة `/admin` أو `/admin/treasuries`. أضف خزائن نقدية/مصرفية متعددة، ثم سجّل قبضاً أو صرفاً أو مصروفاً أو تحويلاً بين خزنتين.
- رصيد كل خزنة محسوب مباشرة من سطور اليومية المرتبطة بها، ولا يُخزّن كرقم قابل للتعديل. يمنع النظام الصرف أو التحويل إذا كان الرصيد غير كافٍ، ويمنع التحويل بين عملتين مختلفتين (العملة الحالية IQD).
- كل حركة تُرحّل بقيد مزدوج متوازن، وكل قيد يحفظ المرجع والتاريخ والمستخدم. لا تُعدّل القيود المرحلة يدوياً؛ صحح الخطأ بحركة عكسية موثقة.
- للمصروفات اختر حساباً من نوع «مصروف». يمكن اختيار حساب ذمم الموردين عند تسجيل استلام بضاعة ثم سداد الذمم من حركة الخزنة. لا تتضمن هذه المرحلة دفاتر فرعية بأسماء الموردين أو العملاء أو إدارة الفواتير الآجلة.

### شجرة الحسابات والتقارير المالية

شجرة الحسابات متاحة في `/admin/chart-accounts` وتدعم حسابات رئيسية وفرعية متعددة المستويات. الحساب التجميعي لا يقبل قيوداً، وحذف الحساب الذي له قيود أو خزائن ممنوع. الحسابات الأساسية التي ينشئها النظام تشمل النقدية، المخزون، حقوق الملكية، المبيعات، تكلفة البضاعة، وفروقات المخزون والمصروفات.

صفحة **التقارير المالية** في `/admin/financial-reports` تعرض:

- ميزان مراجعة تراكمي حتى تاريخ النهاية، متضمناً الأرصدة الافتتاحية، مع مجموع المدين والدائن.
- قائمة دخل للفترة المحددة: الإيرادات، المصروفات وصافي الربح التقديري.
- ميزانية عمومية حتى تاريخ النهاية، وتضم النتيجة المتراكمة ضمن حقوق الملكية.
- أرصدة الخزائن الحالية، وتقييم المخزون الحالي حسب المخزن.
- تنبيهات الأصناف التي بلغت حد إعادة الطلب أو نزلت عنه (حتى 500 تنبيه في الصفحة).

### المخازن والحركات

من **المخازن والمستودعات** يمكن اختيار أي مخزن أب موجود لإنشاء تفرعات متداخلة. يمنع النظام ربط المخزن بنفسه أو بأحد فروعه، ويمنع حذف المخازن التي لديها أبناء أو مخزون أو مبيعات أو جلسات جرد. أضف المنتجات من **إدارة المنتجات**؛ يمكن إدخال SKU والباركود يدوياً أو تركهما فارغين لتوليدهما تلقائياً، مع تحديد وحدة العرض وحد التنبيه.

في `/admin/stock-movements` أنشئ مستند استلام أو صرف أو تحويل، وأضف عدة أصناف وكميات. تبقى الحركة معلقة ولا تغيّر الرصيد حتى اعتمادها. عند اعتماد الاستلام يُحدّث متوسط التكلفة المتحرك لكل منتج في المخزن:

`المتوسط الجديد = (قيمة الرصيد السابق + قيمة الوارد) ÷ (الكمية السابقة + كمية الوارد)`

وعند التحويل تنتقل تكلفة المصدر المتوسطة إلى الوجهة ويُعاد حساب متوسطها. لا يُنشأ قيد مالي للتحويل الداخلي لأنه لا يغيّر قيمة المخزون الإجمالية. الصرف يستهلك متوسط تكلفة المخزن ويرحّل قيمته إلى حساب مصروف محدد.

### الجرد الآني والسنوي

من `/admin/stocktakes` ابدأ جرداً آنيّاً أو سنوياً لمخزن. يأخذ النظام لقطة كميات الأصناف، ويسجل الكميات المعدودة، ثم يرسل الجلسة للمراجعة. الاعتماد وحده يغيّر المخزون ويرحّل قيد فروقات الجرد؛ الزيادة تُسجل مقابل إيراد فائض الجرد، والنقص مقابل مصروف فروقات المخزون.

الجرد لا يجمّد المخزن حسب الإعداد المختار. لذلك يحسب النظام فرق العد نسبةً إلى لقطة البداية ويطبقه على الرصيد الحالي للمحافظة على الحركات التي تمت بعد اللقطة. راجع الطلبات المعلقة أثناء العد، وأوقف اعتماد الجرد إذا لم تكن الكميات الفعلية محسوبة على نفس توقيت اللقطة.

### الصلاحيات والتشغيل المحلي

صلاحيات الوحدة: `finance.view`, `finance.manage`, `treasuries.view`, `treasuries.manage`, `stock-movements.view`, `stock-movements.create`, `stock-movements.approve`, `stocktakes.view`, `stocktakes.create`, و`stocktakes.approve`. يمنح الـSeeder هذه الصلاحيات لأدوار المدير ومدير النظام، ولا يرفع صلاحية المستخدم العادي.

تتطلب الصفحات اتصال المتصفح بخادم Laravel وMySQL على الجهاز/الشبكة المحلية. بعد تثبيت Composer وNode dependencies وبناء أصول Vite محلياً، لا تحتاج وظائف التشغيل إلى الإنترنت. الكميات المخزنية في هذه المرحلة أعداد صحيحة مع وحدة وصفية (مثل قطعة/علبة)، والعملات المدعومة حالياً IQD؛ لا توجد دفاتر عملاء/موردين تفصيلية أو محاسبة عملات متعددة ضمن هذا النطاق.
