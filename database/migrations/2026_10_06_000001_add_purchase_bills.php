<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_bills', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->jsonb('supplier_snapshot');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('reference', 120);
            $table->string('reference_key', 120);
            $table->date('bill_date');
            $table->string('description', 500);
            $table->decimal('total', 20, 2);
            $table->decimal('printed_tax', 20, 2)->nullable();
            $table->timestampTz('recorded_at');
            $table->unique(['supplier_id', 'reference_key']);
            $table->index(['supplier_id', 'id']);
        });
        DB::statement('ALTER TABLE purchase_bills ADD CHECK (total > 0 AND (printed_tax IS NULL OR (printed_tax >= 0 AND printed_tax <= total)))');
        DB::table('ledger_accounts')->insert([
            ['code' => 'purchase_review', 'name' => 'مشتريات بانتظار التصنيف', 'type' => 'asset'],
            ['code' => 'supplier_payable', 'name' => 'ذمم الموردين', 'type' => 'liability'],
        ]);
        Schema::table('journal_entries', fn (Blueprint $table) => $table->foreignId('purchase_bill_id')->nullable()->unique()->constrained()->restrictOnDelete());
        DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT journal_entries_source_check');
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_source_check CHECK (num_nonnulls(sale_id, shift_cash_transfer_id, purchase_bill_id) = 1)');
    }

    public function down(): void
    {
        if (DB::table('purchase_bills')->exists()) {
            throw new LogicException('Posted supplier bills require a forward migration; preserve their provenance.');
        }
        DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT journal_entries_source_check');
        Schema::table('journal_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('purchase_bill_id'));
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_source_check CHECK (num_nonnulls(sale_id, shift_cash_transfer_id) = 1)');
        Schema::dropIfExists('purchase_bills');
        DB::table('ledger_accounts')->whereIn('code', ['purchase_review', 'supplier_payable'])->delete();
    }
};
