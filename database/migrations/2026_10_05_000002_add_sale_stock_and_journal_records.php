<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 3)->default(0);
            $table->timestamps();
        });
        Schema::create('stock_openings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->date('opening_date');
            $table->string('reference', 120);
            $table->timestamps();
        });
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('station_id')->constrained('pos_stations')->restrictOnDelete();
            $table->decimal('net', 20, 2);
            $table->decimal('tax', 20, 2);
            $table->decimal('total', 20, 2);
            $table->decimal('cash_tender', 20, 2);
            $table->decimal('change', 20, 2);
            $table->string('fiscal_status', 24)->default('not_issued');
            $table->timestampTz('recorded_at')->useCurrent();
            $table->index(['cashier_id', 'recorded_at']);
            $table->index(['station_id', 'recorded_at']);
        });
        Schema::create('sale_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('barcode', 64);
            $table->string('unit', 24);
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('net', 20, 2);
            $table->decimal('tax', 20, 2);
            $table->decimal('total', 20, 2);
            $table->unique(['sale_id', 'product_id']);
        });
        Schema::create('sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->string('method', 12);
            $table->decimal('amount', 20, 2);
            $table->string('reference', 64)->nullable();
            $table->boolean('manually_confirmed')->default(false);
            $table->unique(['sale_id', 'method']);
        });
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_line_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('stock_opening_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['product_id', 'created_at']);
        });
        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->string('code', 40)->primary();
            $table->string('name', 80);
            $table->string('type', 16);
        });
        DB::table('ledger_accounts')->insert([
            ['code' => 'cash', 'name' => 'النقدية', 'type' => 'asset'],
            ['code' => 'card_clearing', 'name' => 'تسوية مدفوعات البطاقات', 'type' => 'asset'],
            ['code' => 'output_vat', 'name' => 'ضريبة المبيعات', 'type' => 'liability'],
            ['code' => 'sales', 'name' => 'إيرادات المبيعات', 'type' => 'income'],
        ]);
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('posting_date');
            $table->string('description', 160);
            $table->timestampTz('created_at')->useCurrent();
        });
        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->string('account_code', 40);
            $table->foreign('account_code')->references('code')->on('ledger_accounts')->restrictOnDelete();
            $table->decimal('debit', 20, 2)->default(0);
            $table->decimal('credit', 20, 2)->default(0);
        });
        DB::statement('ALTER TABLE inventory_balances ADD CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE stock_openings ADD CHECK (quantity > 0)');
        DB::statement('ALTER TABLE sales ADD CHECK (net >= 0 AND tax >= 0 AND total > 0 AND total = net + tax AND cash_tender >= 0 AND change >= 0 AND change <= cash_tender)');
        DB::statement('ALTER TABLE sale_lines ADD CHECK (quantity > 0 AND unit_price >= 0 AND net >= 0 AND tax >= 0 AND total = net + tax)');
        DB::statement("ALTER TABLE sale_payments ADD CHECK (amount > 0 AND method IN ('cash', 'card') AND (method <> 'card' OR manually_confirmed))");
        DB::statement('ALTER TABLE stock_movements ADD CHECK (num_nonnulls(sale_line_id, stock_opening_id) = 1 AND ((sale_line_id IS NOT NULL AND quantity < 0) OR (stock_opening_id IS NOT NULL AND quantity > 0)))');
        DB::statement('ALTER TABLE journal_lines ADD CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))');
    }

    public function down(): void
    {
        foreach (['journal_lines', 'journal_entries', 'ledger_accounts', 'stock_movements', 'sale_payments', 'sale_lines', 'sales', 'stock_openings', 'inventory_balances'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
