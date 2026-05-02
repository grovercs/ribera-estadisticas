<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('erp_families', function (Blueprint $table) {
            $table->id();
            $table->string('cod_familia', 4)->unique();
            $table->string('descripcion', 40)->nullable();
            $table->string('codigo_contable_compras', 9)->nullable();
            $table->string('codigo_contable_ventas', 9)->nullable();
            $table->string('rowguid', 36)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_families');
    }
};
