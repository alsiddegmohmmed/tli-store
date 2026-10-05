<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_cash_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->foreignId('shift_id')->constrained('cashier_shifts')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('direction', 3);
            $table->decimal('amount', 20, 2);
            $table->string('reference', 120);
            $table->string('reason', 500);
            $table->timestampTz('recorded_at');
            $table->index(['shift_id', 'id']);
        });
        DB::statement("ALTER TABLE shift_cash_transfers ADD CHECK (amount > 0 AND direction IN ('in', 'out'))");
        DB::table('ledger_accounts')->insert(['code' => 'cash_reserve', 'name' => 'الخزنة الرئيسية', 'type' => 'asset']);
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('sale_id')->nullable()->change();
            $table->foreignId('shift_cash_transfer_id')->nullable()->unique()->constrained('shift_cash_transfers')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_source_check CHECK (num_nonnulls(sale_id, shift_cash_transfer_id) = 1)');

        Schema::table('cashier_shifts', function (Blueprint $table): void {
            $table->decimal('cash_in', 20, 2)->nullable();
            $table->decimal('cash_out', 20, 2)->nullable();
        });
        // Preserve prior closing values; no transfers existed for those snapshots.
        DB::table('cashier_shifts')->whereNotNull('closed_at')->update(['cash_in' => '0.00', 'cash_out' => '0.00']);
        DB::statement('ALTER TABLE cashier_shifts DROP CONSTRAINT cashier_shifts_check');
        DB::statement('ALTER TABLE cashier_shifts ADD CONSTRAINT cashier_shifts_check CHECK (opening_cash >= 0 AND ((closed_at IS NULL AND num_nonnulls(sale_count, sales_total, cash_sales, card_sales, cash_in, cash_out, expected_cash, counted_cash, difference, closing_note) = 0) OR (closed_at IS NOT NULL AND closed_at >= opened_at AND num_nonnulls(sale_count, sales_total, cash_sales, card_sales, cash_in, cash_out, expected_cash, counted_cash, difference) = 9 AND sale_count >= 0 AND cash_sales >= 0 AND card_sales >= 0 AND cash_in >= 0 AND cash_out >= 0 AND sales_total = cash_sales + card_sales AND expected_cash = opening_cash + cash_sales + cash_in - cash_out AND expected_cash >= 0 AND counted_cash >= 0 AND difference = counted_cash - expected_cash)))');
    }

    public function down(): void
    {
        if (DB::table('shift_cash_transfers')->exists()) {
            throw new LogicException('Posted cash transfers require a forward migration; preserve their provenance.');
        }
        DB::statement('ALTER TABLE cashier_shifts DROP CONSTRAINT cashier_shifts_check');
        Schema::table('cashier_shifts', fn (Blueprint $table) => $table->dropColumn(['cash_in', 'cash_out']));
        DB::statement('ALTER TABLE cashier_shifts ADD CONSTRAINT cashier_shifts_check CHECK (opening_cash >= 0 AND ((closed_at IS NULL AND num_nonnulls(sale_count, sales_total, cash_sales, card_sales, expected_cash, counted_cash, difference, closing_note) = 0) OR (closed_at IS NOT NULL AND closed_at >= opened_at AND num_nonnulls(sale_count, sales_total, cash_sales, card_sales, expected_cash, counted_cash, difference) = 7 AND sale_count >= 0 AND cash_sales >= 0 AND card_sales >= 0 AND sales_total = cash_sales + card_sales AND expected_cash = opening_cash + cash_sales AND counted_cash >= 0 AND difference = counted_cash - expected_cash)))');
        DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT journal_entries_source_check');
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shift_cash_transfer_id');
            $table->unsignedBigInteger('sale_id')->nullable(false)->change();
        });
        Schema::dropIfExists('shift_cash_transfers');
        DB::table('ledger_accounts')->where('code', 'cash_reserve')->delete();
    }
};
