<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>نقطة البيع | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="pos-shell">
    <div
        x-data='posCheckout({
            warehouses: @js($warehouses),
            warehouseId: @js($defaultWarehouseId),
            treasuries: @js($treasuries),
            treasuryId: @js($defaultTreasuryId),
            bankTreasuryId: '',
            endpoints: {
                products: @js(route("pos.products")),
                checkout: @js(route("pos.checkout"))
            },
            csrfToken: @js(csrf_token())
        })'
    >
        <header class="pos-header">
            <div>
                <h1>نقطة البيع</h1>
                <small>نظام محلي — لا يحتاج اتصالاً بالإنترنت</small>
            </div>
            <nav class="pos-header-links">
                <a href="{{ route('pos.sales') }}">فواتير البيع</a>
                @if ($canManageSettings)
                    <a href="{{ route('pos.settings') }}">إعدادات نقطة البيع</a>
                @endif
                <a href="{{ url('/admin') }}">العودة إلى لوحة الإدارة</a>
            </nav>
        </header>

        <main class="pos-main">
            <section class="pos-panel" aria-labelledby="product-entry-title">
                <h2 id="product-entry-title">إضافة المنتجات</h2>

                <label class="pos-field">
                    المخزن
                    <select x-model="warehouseId" @change="switchWarehouse()" :disabled="busy || cart.length > 0">
                        <template x-for="warehouse in warehouses" :key="warehouse.id">
                            <option :value="String(warehouse.id)" x-text="warehouse.name"></option>
                        </template>
                    </select>
                </label>

                <p class="pos-alert pos-alert-error" x-show="error" x-text="error" role="alert"></p>
                <p class="pos-alert pos-alert-success" x-show="notice" x-text="notice" role="status"></p>

                <template x-if="warehouses.length === 0">
                    <p class="pos-alert pos-alert-error">لا يوجد مخزن نشط. فعّل مخزناً أولاً من لوحة الإدارة.</p>
                </template>

                <label class="pos-field pos-scanner">
                    امسح الباركود
                    <input
                        x-ref="barcodeInput"
                        x-model="barcode"
                        @keydown.enter.prevent="scanBarcode()"
                        type="text"
                        inputmode="none"
                        autocomplete="off"
                        placeholder="ضع المؤشر هنا ثم امسح الباركود"
                        :disabled="warehouses.length === 0 || busy"
                    >
                </label>
                <p class="pos-help">الماسح الذي يعمل كلوحة مفاتيح يُدخل الرقم ثم يضغط Enter تلقائياً.</p>

                <label class="pos-field">
                    البحث بالاسم أو الرمز
                    <input
                        x-model="search"
                        @input.debounce.250ms="searchProducts()"
                        type="search"
                        autocomplete="off"
                        placeholder="اكتب حرفين على الأقل"
                        :disabled="warehouses.length === 0 || busy"
                    >
                </label>

                <div class="pos-results" x-show="searchResults.length">
                    <template x-for="product in searchResults" :key="product.id">
                        <div class="pos-result">
                            <div>
                                <strong x-text="product.name"></strong>
                                <small x-text="`${product.barcode} — متوفر: ${product.stock}`"></small>
                                <small x-text="money(Number(product.price))"></small>
                            </div>
                            <button type="button" @click="addProduct(product)">إضافة</button>
                        </div>
                    </template>
                </div>
            </section>

            <section class="pos-panel" aria-labelledby="cart-title">
                <h2 id="cart-title">السلة (<span x-text="totalQuantity"></span> قطعة)</h2>

                <div class="pos-cart" x-show="cart.length">
                    <template x-for="item in cart" :key="item.id">
                        <article class="pos-cart-row">
                            <div>
                                <strong x-text="item.name"></strong>
                                <small x-text="item.barcode"></small>
                                <small x-text="`${money(Number(item.price))} × ${item.quantity} = ${money(Number(item.price) * item.quantity)}`"></small>
                            </div>
                            <div class="pos-quantity" :aria-label="`الكمية ${item.quantity}`">
                                <button type="button" @click="changeQuantity(item, 1)" :aria-label="`زيادة ${item.name}`">+</button>
                                <span x-text="item.quantity"></span>
                                <button type="button" @click="changeQuantity(item, -1)" :aria-label="`تقليل ${item.name}`">−</button>
                                <button type="button" @click="removeProduct(item.id)" :aria-label="`حذف ${item.name}`">×</button>
                            </div>
                        </article>
                    </template>
                </div>
                <p class="pos-empty" x-show="cart.length === 0">السلة فارغة. امسح باركوداً أو ابحث عن منتج.</p>

                <label class="pos-field">
                    اسم العميل (اختياري)
                    <input x-model="customerName" type="text" maxlength="120" autocomplete="name" :disabled="busy">
                </label>

                <label class="pos-field">
                    هاتف العميل (اختياري)
                    <input x-model="customerPhone" type="tel" maxlength="40" autocomplete="tel" dir="ltr" :disabled="busy">
                </label>

                <label class="pos-field">
                    طريقة الدفع
                    <select x-model="paymentMethod" :disabled="busy">
                        <option value="cash">نقداً</option>
                        <option value="bank_app">تطبيق بنكي</option>
                        <option value="mixed">نقداً + تطبيق بنكي</option>
                    </select>
                </label>

                <label class="pos-field" x-show="paymentMethod !== 'bank_app'">
                    خزنة استلام النقد
                    <select x-model="treasuryId" :disabled="busy || treasuries.length === 0" required>
                        <template x-for="treasury in treasuries" :key="treasury.id">
                            <option :value="String(treasury.id)" x-text="`${treasury.name} (${treasury.currency})`"></option>
                        </template>
                    </select>
                </label>
                <label class="pos-field" x-show="paymentMethod !== 'cash'">
                    خزنة / حساب إيداع التطبيق البنكي
                    <select x-model="bankTreasuryId" :disabled="busy || treasuries.length === 0" :required="paymentMethod !== 'cash'">
                        <option value="">اختر حساب التطبيق البنكي</option>
                        <template x-for="treasury in treasuries" :key="treasury.id">
                            <option :value="String(treasury.id)" x-text="`${treasury.name} (${treasury.currency})`"></option>
                        </template>
                    </select>
                </label>

                <label class="pos-field" x-show="paymentMethod === 'mixed'">
                    المبلغ النقدي
                    <input x-model.number="cashAmount" type="number" min="0.01" :max="total" step="0.01" :disabled="busy">
                </label>
                <p class="pos-help" x-show="paymentMethod === 'mixed'">
                    المتبقي على التطبيق البنكي:
                    <strong x-text="money(bankPaymentAmount)"></strong>
                </p>
                <p class="pos-help" x-show="treasuries.length === 0">لا توجد خزنة نشطة؛ أنشئ خزنة من صفحة الخزائن قبل البيع.</p>

                <div class="pos-summary">
                    <span>الإجمالي</span>
                    <span x-text="money(total)"></span>
                </div>

                <button
                    type="button"
                    class="pos-button pos-button-full"
                    @click="checkout()"
                    :disabled="cart.length === 0 || busy || warehouses.length === 0 || treasuries.length === 0 || (paymentMethod !== 'cash' && !bankTreasuryId) || (paymentMethod === 'mixed' && (cashAmount <= 0 || cashAmount >= total))"
                >
                    <span x-show="!busy">إتمام البيع وطباعة الإيصال</span>
                    <span x-show="busy">جارٍ حفظ البيع...</span>
                </button>
                <p class="pos-help">السلة تُحفظ محلياً في هذا المتصفح، ويُعتمد السعر والمخزون من الخادم عند إتمام البيع.</p>
            </section>
        </main>
    </div>
</body>
</html>
