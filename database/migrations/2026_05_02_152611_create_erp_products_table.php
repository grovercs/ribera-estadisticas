<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_products', function (Blueprint $table) {
            $table->id();
            $table->string('cod_articulo', 20)->unique();
            $table->string('marca', 40)->nullable();
            $table->string('cod_barras', 20)->nullable();
            $table->string('cod_familia', 10)->nullable()->index();
            $table->string('cod_subfamilia', 10)->nullable()->index();
            $table->smallInteger('cod_impuesto')->nullable();
            $table->string('inventariable', 1)->nullable();
            $table->decimal('unidades_venta', 19, 6)->nullable();
            $table->string('cod_unidad', 3)->nullable();
            $table->integer('cod_proveedor_activo')->nullable();
            $table->dateTime('fecha_alta')->nullable();
            $table->dateTime('fecha_baja')->nullable();
            $table->decimal('precio_coste', 19, 6)->nullable();
            $table->decimal('incremento', 9, 3)->nullable();
            $table->decimal('cargo', 9, 3)->nullable();
            $table->decimal('precio_venta_base', 19, 6)->nullable();
            $table->decimal('precio_venta_publico', 19, 6)->nullable();
            $table->decimal('precio_medio_ponderado', 19, 6)->nullable();
            $table->string('advertencia', 255)->nullable();
            $table->text('observaciones')->nullable();
            $table->string('kit', 1)->nullable();
            $table->decimal('precio_venta_por', 19, 6)->nullable();
            $table->string('cod_gama', 10)->nullable();
            $table->decimal('descuento_maximo', 9, 3)->nullable();
            $table->decimal('pvb_redondeado', 19, 6)->nullable();
            $table->decimal('pvp_redondeado', 19, 6)->nullable();
            $table->dateTime('fecha_precio_venta')->nullable();
            $table->dateTime('fecha_precio_venta_anterior')->nullable();
            $table->decimal('pvb_anterior', 19, 6)->nullable();
            $table->decimal('pvp_anterior', 19, 6)->nullable();
            $table->decimal('raee', 19, 6)->nullable();
            $table->string('cod_familia_web', 10)->nullable();
            $table->string('cod_subfamilia_web', 10)->nullable();
            $table->string('cod_marca_web', 10)->nullable();
            $table->decimal('peso_web', 19, 6)->nullable();
            $table->string('tipo_articulo', 1)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_products');
    }
};
