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
    Schema::create('product_warehouse', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id')->constrained()->onDelete('cascade');
        $table->foreignId('warehouse_id')->constrained()->onDelete('cascade'); // المخزن الرئيسي أو الفرعي
        
        $table->integer('quantity')->default(0); // كمية المنتج المتوفرة في هذا المخزن بالتحديد
        $table->string('shelf_number')->nullable(); // رقم الرف التخزيني الداخلي
        $table->timestamps();

        // فهرسة مركبة لسرعة جلب كميات المخازن أوفلاين
        $table->unique(['product_id', 'warehouse_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_warehouse');
    }
};
