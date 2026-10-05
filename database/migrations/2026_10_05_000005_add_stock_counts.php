<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_balances', fn (Blueprint $table) => $table->unsignedBigInteger('revision')->default(0));
        DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT inventory_balances_revision_check CHECK (revision >= 0)');
        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->jsonb('product_snapshot');
            $table->decimal('recorded_quantity', 18, 3);
            $table->decimal('counted_quantity', 18, 3);
            $table->decimal('difference', 18, 3);
            $table->unsignedBigInteger('balance_revision');
            $table->string('reference', 120);
            $table->string('reason', 500);
            $table->timestampTz('recorded_at')->useCurrent();
            $table->index(['product_id', 'recorded_at']);
        });
        DB::statement('ALTER TABLE stock_counts ADD CHECK (recorded_quantity >= 0 AND counted_quantity >= 0 AND balance_revision >= 0 AND difference = counted_quantity - recorded_quantity)');
        Schema::table('stock_movements', fn (Blueprint $table) => $table->foreignId('stock_count_id')->nullable()->unique()->constrained()->restrictOnDelete());
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_check');
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_check CHECK (num_nonnulls(sale_line_id, stock_opening_id, stock_count_id) = 1 AND ((sale_line_id IS NOT NULL AND quantity < 0) OR (stock_opening_id IS NOT NULL AND quantity > 0) OR (stock_count_id IS NOT NULL AND quantity <> 0)))');
    }

    public function down(): void
    {
        // Development schema rollback removes count provenance; never use it on posted store records.
        DB::table('stock_movements')->whereNotNull('stock_count_id')->delete();
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_check');
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropConstrainedForeignId('stock_count_id'));
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_check CHECK (num_nonnulls(sale_line_id, stock_opening_id) = 1 AND ((sale_line_id IS NOT NULL AND quantity < 0) OR (stock_opening_id IS NOT NULL AND quantity > 0)))');
        Schema::dropIfExists('stock_counts');
        Schema::table('inventory_balances', fn (Blueprint $table) => $table->dropColumn('revision'));
    }
};
