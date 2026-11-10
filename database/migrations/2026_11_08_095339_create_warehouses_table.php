<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم المخزن الرئيسي أو الفرعي
            
            // الربط الذاتي: حقل يشير إلى رقم المخزن الأب (يكون فارغاً Nullable إذا كان مخزناً رئيسياً)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('warehouses')
                ->onDelete('cascade'); // إذا حُذف الرئيسي تُحذف الفروع تلقائياً للأمان
                
            $table->string('location')->nullable(); // موقع المخزن المادي أو الرف
            $table->boolean('is_active')->default(true); // حالة المخزن (نشط / موقف)
            $table->timestamps();

            // فهرسة برمجية لضمان سرعة التقارير الفورية أوفلاين
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
