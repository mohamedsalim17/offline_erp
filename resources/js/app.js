import './bootstrap';
import Alpine from 'alpinejs';
import '../css/pos.css';

window.Alpine = Alpine;

window.posCheckout = (config) => ({
    warehouses: config.warehouses,
    warehouseId: String(config.warehouseId ?? ''),
    treasuries: config.treasuries ?? [],
    treasuryId: String(config.treasuryId ?? ''),
    bankTreasuryId: String(config.bankTreasuryId ?? ''),
    endpoints: config.endpoints,
    csrfToken: config.csrfToken,
    barcode: '',
    search: '',
    searchResults: [],
    cart: [],
    paymentMethod: 'cash',
    cashAmount: 0,
    customerName: '',
    customerPhone: '',
    busy: false,
    error: '',
    notice: '',
    pendingCheckout: null,

    init() {
        this.loadCart();
        this.$nextTick(() => this.$refs.barcodeInput?.focus());
    },

    storageKey() {
        return `offline-pos-cart-${this.warehouseId}`;
    },

    loadCart() {
        try {
            const saved = localStorage.getItem(this.storageKey());
            const state = saved ? JSON.parse(saved) : null;
            this.cart = Array.isArray(state?.cart) ? state.cart : [];
            this.pendingCheckout = state?.pendingCheckout ?? null;
        } catch {
            this.cart = [];
            this.pendingCheckout = null;
            this.error = 'تعذر استعادة السلة المحفوظة؛ ابدأ سلة جديدة.';
        }
    },

    saveCart() {
        try {
            localStorage.setItem(this.storageKey(), JSON.stringify({
                cart: this.cart,
                pendingCheckout: this.pendingCheckout,
            }));
        } catch {
            this.error = 'تعذر حفظ السلة في هذا المتصفح. لا تحدّث الصفحة قبل إتمام البيع.';
        }
    },

    switchWarehouse() {
        this.cart = [];
        this.pendingCheckout = null;
        this.searchResults = [];
        this.saveCart();
        this.notice = 'تم تغيير المخزن وبدء سلة مستقلة.';
    },

    async scanBarcode() {
        const barcode = this.barcode.trim();
        if (!barcode || this.busy) return;

        this.error = '';
        this.notice = '';
        try {
            const url = new URL(this.endpoints.products, window.location.origin);
            url.searchParams.set('warehouse_id', this.warehouseId);
            url.searchParams.set('barcode', barcode);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'تعذر البحث عن الباركود.');
            if (!result.data.length) throw new Error('المنتج غير موجود في المخزن المحدد أو رصيده صفر.');
            this.addProduct(result.data[0]);
            this.barcode = '';
            this.$refs.barcodeInput?.focus();
        } catch (error) {
            this.error = error.message || 'تعذر الاتصال بخادم النظام المحلي.';
            this.$refs.barcodeInput?.select();
        }
    },

    async searchProducts() {
        const search = this.search.trim();
        if (search.length < 2) {
            this.searchResults = [];
            return;
        }

        try {
            const url = new URL(this.endpoints.products, window.location.origin);
            url.searchParams.set('warehouse_id', this.warehouseId);
            url.searchParams.set('search', search);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'تعذر البحث عن المنتجات.');
            this.searchResults = result.data;
            this.error = '';
        } catch (error) {
            this.error = error.message || 'تعذر الاتصال بخادم النظام المحلي.';
        }
    },

    addProduct(product) {
        const existing = this.cart.find((item) => item.id === product.id);
        const quantity = (existing?.quantity ?? 0) + 1;
        if (quantity > product.stock) {
            this.error = `الكمية المتاحة من "${product.name}" هي ${product.stock} فقط.`;
            return;
        }

        if (existing) {
            existing.quantity = quantity;
            existing.stock = product.stock;
        } else {
            this.cart.push({
                id: product.id,
                name: product.name,
                barcode: product.barcode,
                sku: product.sku,
                price: product.price,
                stock: product.stock,
                quantity: 1,
            });
        }
        this.pendingCheckout = null;
        this.error = '';
        this.saveCart();
    },

    changeQuantity(item, adjustment) {
        const quantity = item.quantity + adjustment;
        if (quantity < 1) {
            this.removeProduct(item.id);
            return;
        }
        if (quantity > item.stock) {
            this.error = `الكمية المتاحة من "${item.name}" هي ${item.stock} فقط.`;
            return;
        }
        item.quantity = quantity;
        this.pendingCheckout = null;
        this.error = '';
        this.saveCart();
    },

    removeProduct(id) {
        this.cart = this.cart.filter((item) => item.id !== id);
        this.pendingCheckout = null;
        this.saveCart();
    },

    get totalQuantity() {
        return this.cart.reduce((total, item) => total + item.quantity, 0);
    },

    get total() {
        return this.cart.reduce((total, item) => total + (Number(item.price) * item.quantity), 0);
    },

    get cashPaymentAmount() {
        if (this.paymentMethod === 'cash') return this.total;
        if (this.paymentMethod === 'bank_app') return 0;
        return Math.round(Number(this.cashAmount) * 100) / 100;
    },

    get bankPaymentAmount() {
        return Math.round((this.total - this.cashPaymentAmount) * 100) / 100;
    },

    money(amount) {
        return new Intl.NumberFormat('ar-IQ', {
            maximumFractionDigits: 2,
        }).format(amount) + ' د.ع';
    },

    createRequestKey() {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
            const random = Math.random() * 16 | 0;
            return (character === 'x' ? random : (random & 0x3 | 0x8)).toString(16);
        });
    },

    async checkout() {
        if (!this.cart.length || this.busy) return;

        const payload = {
            warehouse_id: Number(this.warehouseId),
            payment_method: this.paymentMethod,
            customer_name: this.customerName.trim() || null,
            customer_phone: this.customerPhone.trim() || null,
            cash_amount: this.cashPaymentAmount.toFixed(2),
            bank_amount: this.bankPaymentAmount.toFixed(2),
            treasury_id: this.paymentMethod === 'bank_app' ? null : (this.treasuryId ? Number(this.treasuryId) : null),
            bank_treasury_id: this.paymentMethod === 'cash' ? null : (this.bankTreasuryId ? Number(this.bankTreasuryId) : null),
            items: this.cart.map((item) => ({ product_id: item.id, quantity: item.quantity })),
        };
        const fingerprint = JSON.stringify(payload);
        if (this.pendingCheckout?.fingerprint !== fingerprint) {
            this.pendingCheckout = {
                fingerprint,
                requestKey: this.createRequestKey(),
            };
        }
        this.saveCart();

        const printWindow = window.open('', '_blank');
        this.busy = true;
        this.error = '';
        this.notice = '';

        try {
            const response = await fetch(this.endpoints.checkout, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    ...payload,
                    request_key: this.pendingCheckout.requestKey,
                }),
            });
            const result = await response.json();
            if (!response.ok) {
                const validationMessage = result.errors
                    ? Object.values(result.errors).flat()[0]
                    : result.message;
                throw new Error(validationMessage || 'تعذر إتمام عملية البيع.');
            }

            this.cart = [];
            this.pendingCheckout = null;
            this.saveCart();
            this.notice = `تم البيع بنجاح. رقم الإيصال: ${result.receipt_number}`;
            if (printWindow) {
                printWindow.location = result.receipt_url;
            } else {
                window.location.assign(result.receipt_url);
            }
        } catch (error) {
            printWindow?.close();
            this.error = error.message || 'تعذر الاتصال بخادم النظام المحلي. بقيت السلة محفوظة.';
        } finally {
            this.busy = false;
        }
    },
});

Alpine.start();
