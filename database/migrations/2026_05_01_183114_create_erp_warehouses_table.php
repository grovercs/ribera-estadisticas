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
        Schema::create('erp_warehouses', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('cod_almacen')->unique();
            $table->string('nombre', 40)->nullable();
            $table->string('direccion1', 40)->nullable();
            $table->string('direccion2', 40)->nullable();
            $table->string('cp', 8)->nullable();
            $table->string('poblacion', 40)->nullable();
            $table->string('provincia', 40)->nullable();
            $table->string('cod_pais', 2)->nullable();
            $table->string('telefono', 15)->nullable();
            $table->string('fax', 15)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('persona_contacto', 40)->nullable();
            $table->dateTime('fecha_ultimo_inventario')->nullable();
            $table->string('rowguid', 36)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_warehouses');
    }
};
