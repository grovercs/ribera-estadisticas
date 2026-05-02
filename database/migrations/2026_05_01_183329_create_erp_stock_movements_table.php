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
        Schema::create('erp_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('cod_articulo', 15)->index();
            $table->smallInteger('cod_empresa')->nullable();
            $table->integer('cod_documento')->nullable();
            $table->smallInteger('tipo_documento')->nullable();
            $table->smallInteger('cod_caja')->nullable();
            $table->string('operacion', 1)->nullable(); // E/S
            $table->integer('cod_cliente')->nullable()->index();
            $table->integer('cod_proveedor')->nullable();
            $table->dateTime('fecha')->nullable()->index();
            $table->string('hora', 5)->nullable();
            $table->smallInteger('cod_almacen')->nullable()->index();
            $table->decimal('cantidad', 19, 6)->nullable();
            $table->smallInteger('linea')->nullable();
            $table->string('cod_movimiento', 36)->nullable()->unique();
            $table->smallInteger('linea_kit')->nullable();
            $table->string('rowguid', 36)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_stock_movements');
    }
};
