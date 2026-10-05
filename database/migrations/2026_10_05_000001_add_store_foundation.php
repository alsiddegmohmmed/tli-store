<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('cashier');
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('barcode', 64)->unique();
            $table->string('name', 120);
            $table->string('unit', 24);
            $table->decimal('price', 12, 2);
            $table->decimal('tax_rate', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('pos_stations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_stations');
        Schema::dropIfExists('products');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
