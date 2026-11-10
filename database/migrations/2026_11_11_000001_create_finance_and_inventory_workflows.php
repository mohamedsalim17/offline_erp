<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->foreign('parent_id')->references('id')->on('warehouses')->restrictOnDelete();
        });

        if (! Schema::hasColumn('products', 'unit_name')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('unit_name', 40)->default('قطعة')->after('barcode');
            });
        }

        if (! Schema::hasColumn('product_warehouse', 'average_cost')) {
            Schema::table('product_warehouse', function (Blueprint $table) {
                $table->decimal('average_cost', 14, 2)->default(0)->after('quantity');
            });
        }

        \Illuminate\Support\Facades\DB::table('product_warehouse')
            ->where('average_cost', 0)
            ->orderBy('id')
            ->get(['id', 'product_id'])
            ->each(function (object $stock): void {
                $cost = \Illuminate\Support\Facades\DB::table('products')
                    ->where('id', $stock->product_id)
                    ->value('cost_price');

                \Illuminate\Support\Facades\DB::table('product_warehouse')
                    ->where('id', $stock->id)
                    ->update(['average_cost' => $cost ?? 0]);
            });

        $this->createTableIfMissing('chart_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->foreignId('parent_id')->nullable()->constrained('chart_accounts')->restrictOnDelete();
            $table->string('system_key', 50)->nullable()->unique();
            $table->boolean('is_group')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['parent_id', 'type']);
        });

        $this->createTableIfMissing('treasuries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->foreignId('account_id')->constrained('chart_accounts')->restrictOnDelete();
            $table->string('currency', 3)->default('IQD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->createTableIfMissing('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number', 40)->unique();
            $table->date('entry_date')->index();
            $table->string('description', 255);
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
        });

        $this->createTableIfMissing('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_accounts')->restrictOnDelete();
            $table->foreignId('treasury_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 255)->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->timestamps();
            $table->index(['account_id', 'journal_entry_id']);
            $table->index(['treasury_id', 'journal_entry_id']);
        });

        $this->createTableIfMissing('treasury_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('treasury_id')->constrained()->restrictOnDelete();
            $table->foreignId('destination_treasury_id')->nullable()->constrained('treasuries')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['cash_in', 'cash_out', 'transfer', 'expense']);
            $table->decimal('amount', 14, 2);
            $table->date('transaction_date')->index();
            $table->string('description', 255);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['treasury_id', 'transaction_date']);
        });

        $this->createTableIfMissing('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->enum('type', ['receipt', 'issue', 'transfer', 'stocktake']);
            $table->enum('status', ['pending', 'posted', 'rejected'])->default('pending')->index();
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->date('movement_date')->index();
            $table->string('reason', 255);
            $table->foreignId('offset_account_id')->nullable()->constrained('chart_accounts')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        if (! Schema::hasIndex('stock_movements', 'stock_movements_wh_status_idx')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->index(
                    ['source_warehouse_id', 'destination_warehouse_id', 'status'],
                    'stock_movements_wh_status_idx',
                );
            });
        }

        $this->createTableIfMissing('stock_movement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('direction', 8)->default('in');
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('value', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['stock_movement_id', 'product_id']);
        });

        $this->createTableIfMissing('stocktakes', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['spot', 'annual'])->default('spot');
            $table->enum('status', ['counting', 'submitted', 'approved', 'cancelled'])->default('counting')->index();
            $table->date('count_date');
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'count_date', 'status']);
        });

        $this->createTableIfMissing('stocktake_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stocktake_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('system_quantity');
            $table->unsignedInteger('counted_quantity')->nullable();
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['stocktake_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_items');
        Schema::dropIfExists('stocktakes');
        Schema::dropIfExists('stock_movement_items');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('treasury_transactions');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('treasuries');
        Schema::dropIfExists('chart_accounts');

        Schema::table('product_warehouse', function (Blueprint $table) {
            $table->dropColumn('average_cost');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('unit_name');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->foreign('parent_id')->references('id')->on('warehouses')->cascadeOnDelete();
        });
    }

    private function createTableIfMissing(string $name, \Closure $definition): void
    {
        if (! Schema::hasTable($name)) {
            Schema::create($name, $definition);
        }
    }
};
