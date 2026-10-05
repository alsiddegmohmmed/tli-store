<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('station_id')->constrained('pos_stations')->restrictOnDelete();
            $table->decimal('opening_cash', 20, 2);
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->unsignedInteger('sale_count')->nullable();
            foreach (['sales_total', 'cash_sales', 'card_sales', 'expected_cash', 'counted_cash', 'difference'] as $column) {
                $table->decimal($column, 20, 2)->nullable();
            }
            $table->string('closing_note', 500)->nullable();
            $table->index(['cashier_id', 'opened_at']);
        });
        DB::statement('CREATE UNIQUE INDEX cashier_shifts_open_station_unique ON cashier_shifts (station_id) WHERE closed_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX cashier_shifts_open_cashier_unique ON cashier_shifts (cashier_id) WHERE closed_at IS NULL');
        DB::statement('ALTER TABLE cashier_shifts ADD CHECK (opening_cash >= 0 AND ((closed_at IS NULL AND num_nonnulls(sale_count, sales_total, cash_sales, card_sales, expected_cash, counted_cash, difference, closing_note) = 0) OR (closed_at IS NOT NULL AND closed_at >= opened_at AND num_nonnulls(sale_count, sales_total, cash_sales, card_sales, expected_cash, counted_cash, difference) = 7 AND sale_count >= 0 AND cash_sales >= 0 AND card_sales >= 0 AND sales_total = cash_sales + card_sales AND expected_cash = opening_cash + cash_sales AND counted_cash >= 0 AND difference = counted_cash - expected_cash)))');
        Schema::table('sales', function (Blueprint $table): void {
            // Earlier development sales remain unassigned, outside all shift reconciliations.
            $table->foreignId('shift_id')->nullable()->index()->constrained('cashier_shifts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropConstrainedForeignId('shift_id'));
        Schema::dropIfExists('cashier_shifts');
    }
};
