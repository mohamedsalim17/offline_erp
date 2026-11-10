<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // اسم المنتج
        $table->string('sku')->unique(); // الرمز المخزني
        $table->string('barcode')->unique(); // الباركود
        
        // تفاصيل الحسابات والأسعار المتقدمة
        $table->decimal('cost_price', 10, 2); // سعر التكلفة (حساب رأس المال المخزني)
        $table->decimal('selling_price', 10, 2); // سعر بيع التجزئة (القطاعي)
        $table->decimal('wholesale_price', 10, 2)->nullable(); // سعر بيع الجملة
        
        $table->integer('minimum_stock_alert')->default(5); // حد الطلب
        $table->foreignId('category_id')->constrained()->onDelete('cascade');
        $table->timestamps();

        $table->index('barcode');
        $table->index('sku');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
