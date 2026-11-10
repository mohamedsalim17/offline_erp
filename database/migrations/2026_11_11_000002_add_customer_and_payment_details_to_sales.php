<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->decimal('cash_amount', 12, 2)->default(0);
            $table->decimal('bank_amount', 12, 2)->default(0);
            $table->foreignId('cash_treasury_id')->nullable()->after('warehouse_id')
                ->constrained('treasuries')->restrictOnDelete();
            $table->foreignId('bank_treasury_id')->nullable()->after('warehouse_id')
                ->constrained('treasuries')->restrictOnDelete();
        });

        DB::table('sales')->where('payment_method', 'cash')->update([
            'cash_amount' => DB::raw('total'),
        ]);
        DB::table('sales')->whereIn('payment_method', ['card', 'transfer'])->update([
            'payment_method' => 'bank_app',
            'bank_amount' => DB::raw('total'),
        ]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_treasury_id');
            $table->dropConstrainedForeignId('bank_treasury_id');
            $table->dropColumn(['customer_name', 'customer_phone', 'cash_amount', 'bank_amount']);
        });
    }
};
