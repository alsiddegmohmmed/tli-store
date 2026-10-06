<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_bill_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('kind', 24);
            $table->string('original_name', 255);
            $table->string('storage_path', 255);
            $table->string('mime_type', 40);
            $table->string('extension', 4);
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->string('paper_location', 200)->nullable();
            $table->timestampTz('recorded_at');
        });
        DB::statement('ALTER TABLE document_evidence ADD CHECK (num_nonnulls(purchase_bill_id, supplier_payment_id) = 1 AND size > 0)');
        DB::statement('CREATE UNIQUE INDEX document_evidence_bill_hash_unique ON document_evidence (purchase_bill_id, sha256) WHERE purchase_bill_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX document_evidence_payment_hash_unique ON document_evidence (supplier_payment_id, sha256) WHERE supplier_payment_id IS NOT NULL');
    }

    public function down(): void
    {
        if (DB::table('document_evidence')->exists()) {
            throw new LogicException('Stored evidence requires a forward migration; preserve file provenance.');
        }
        Schema::dropIfExists('document_evidence');
    }
};
