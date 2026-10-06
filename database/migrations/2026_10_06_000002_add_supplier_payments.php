<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('purchase_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 20, 2);
            $table->string('method', 16);
            $table->string('reference', 120);
            $table->string('reference_key', 120);
            $table->string('funding_reference', 120);
            $table->string('actual_payer', 120);
            $table->timestampTz('recorded_at');
            $table->unique(['purchase_bill_id', 'method', 'reference_key'], 'supplier_payments_document_unique');
            $table->index(['purchase_bill_id', 'id']);
        });
        DB::statement("ALTER TABLE supplier_payments ADD CHECK (amount > 0 AND method IN ('cash_reserve', 'bank'))");
        DB::table('ledger_accounts')->insert(['code' => 'company_bank', 'name' => 'بنوك الشركة', 'type' => 'asset']);
        Schema::table('journal_entries', fn (Blueprint $table) => $table->foreignId('supplier_payment_id')->nullable()->unique()->constrained()->restrictOnDelete());
        DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT journal_entries_source_check');
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_source_check CHECK (num_nonnulls(sale_id, shift_cash_transfer_id, purchase_bill_id, supplier_payment_id) = 1)');
    }

    public function down(): void
    {
        if (DB::table('supplier_payments')->exists()) {
            throw new LogicException('Posted supplier payments require a forward migration; preserve their provenance.');
        }
        DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT journal_entries_source_check');
        Schema::table('journal_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('supplier_payment_id'));
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_source_check CHECK (num_nonnulls(sale_id, shift_cash_transfer_id, purchase_bill_id) = 1)');
        Schema::dropIfExists('supplier_payments');
        DB::table('ledger_accounts')->where('code', 'company_bank')->delete();
    }
};
