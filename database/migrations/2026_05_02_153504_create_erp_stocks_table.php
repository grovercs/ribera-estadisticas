<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_stocks', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('cod_almacen');
            $table->string('cod_articulo', 20)->index();
            $table->decimal('existencias', 19, 6)->nullable();
            $table->dateTime('fecha_ultima_entrada')->nullable();
            $table->dateTime('fecha_ultima_salida')->nullable();
            $table->decimal('maximos', 19, 6)->nullable();
            $table->decimal('minimos', 19, 6)->nullable();
            $table->string('ubicacion', 50)->nullable();
            $table->dateTime('fecha_ultimo_inventario')->nullable();
            $table->decimal('cantidad_pendiente_servir', 19, 6)->nullable();
            $table->decimal('cantidad_pendiente_recibir', 19, 6)->nullable();
            $table->timestamps();

            $table->unique(['cod_almacen', 'cod_articulo'], 'stock_pk');
            $table->index(['cod_almacen', 'cod_articulo'], 'stock_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_stocks');
    }
};
