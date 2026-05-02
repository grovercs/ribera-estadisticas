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
        Schema::create('erp_products', function (Blueprint $table) {
            $table->id();
            $table->string('cod_articulo', 15)->unique();
            $table->string('marca', 15)->nullable();
            $table->string('cod_barras', 15)->nullable();
            $table->string('cod_familia', 4)->nullable()->index();
            $table->string('cod_subfamilia', 4)->nullable()->index();
            $table->smallInteger('cod_impuesto')->nullable();
            $table->decimal('unidades_venta', 19, 6)->nullable();
            $table->string('cod_unidad', 3)->nullable();
            $table->integer('cod_proveedor_activo')->nullable();
            $table->dateTime('fecha_alta')->nullable();
            $table->dateTime('fecha_baja')->nullable();
            $table->decimal('precio_coste', 19, 6)->nullable();
            $table->decimal('incremento', 9, 3)->nullable();
            $table->decimal('incremento_minimo', 9, 3)->nullable();
            $table->decimal('cargo', 9, 3)->nullable();
            $table->decimal('precio_venta_base', 19, 6)->nullable();
            $table->decimal('precio_venta_publico', 19, 6)->nullable();
            $table->decimal('precio_medio_ponderado', 19, 6)->nullable();
            $table->decimal('descuento_maximo', 9, 3)->nullable();
            $table->string('cod_gama', 4)->nullable();
            $table->decimal('peso_web', 19, 6)->nullable();
            $table->decimal('alto_web', 19, 6)->nullable();
            $table->decimal('ancho_web', 19, 6)->nullable();
            $table->decimal('profundo_web', 19, 6)->nullable();
            $table->string('tipo_articulo', 10)->nullable();
            $table->string('advertencia', 80)->nullable();
            $table->string('observaciones', 255)->nullable();
            $table->string('rowguid', 36)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_products');
    }
};
