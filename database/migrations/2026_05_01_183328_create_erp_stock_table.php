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
        Schema::create('erp_stock', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('cod_almacen');
            $table->string('cod_articulo', 15);
            $table->decimal('existencias', 19, 6)->nullable();
            $table->dateTime('fecha_ultima_entrada')->nullable();
            $table->dateTime('fecha_ultima_salida')->nullable();
            $table->decimal('maximos', 19, 6)->nullable();
            $table->decimal('minimos', 19, 6)->nullable();
            $table->string('ubicacion', 9)->nullable();
            $table->dateTime('fecha_ultimo_inventario')->nullable();
            $table->string('hora_ultimo_inventario', 5)->nullable();
            $table->string('permitir_venta_bajo_stock', 1)->nullable();
            $table->decimal('cantidad_pendiente_servir', 19, 6)->nullable();
            $table->decimal('cantidad_pendiente_recibir', 19, 6)->nullable();
            $table->string('rowguid', 36)->nullable();
            $table->timestamps();

            $table->unique(['cod_almacen', 'cod_articulo']);
            $table->index('cod_articulo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_stock');
    }
};
