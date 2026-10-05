<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('id')->primary()->default(1);
            $table->string('legal_name', 120);
            $table->string('vat_number', 15);
            $table->string('commercial_registration', 20);
            $table->string('street', 120);
            $table->string('building_number', 4);
            $table->string('district', 120);
            $table->string('city', 120);
            $table->string('postal_code', 5);
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE company_profiles ADD CHECK (id = 1)');
        Schema::table('sales', function (Blueprint $table): void {
            // Earlier development sales have unknown seller details; never backfill current values.
            $table->jsonb('seller_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('seller_snapshot'));
        Schema::dropIfExists('company_profiles');
    }
};
