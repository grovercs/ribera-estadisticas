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
        Schema::create('erp_sale_lines', function (Blueprint $table) {
            $table->id();
            $table->integer('cod_venta');
            $table->smallInteger('tipo_venta');
            $table->smallInteger('cod_empresa');
            $table->smallInteger('cod_caja');
            $table->smallInteger('linea');
            $table->string('cod_articulo', 15)->nullable()->index();
            $table->string('descripcion', 255)->nullable();
            $table->decimal('cantidad', 19, 6)->nullable();
            $table->decimal('dto1', 9, 3)->nullable();
            $table->decimal('dto2', 9, 3)->nullable();
            $table->decimal('precio', 19, 6)->nullable();
            $table->decimal('precio_coste', 19, 6)->nullable();
            $table->decimal('cargo', 9, 3)->nullable();
            $table->decimal('importe', 19, 6)->nullable();
            $table->decimal('importe_impuestos', 19, 6)->nullable();
            $table->smallInteger('cod_impuesto')->nullable();
            $table->decimal('porcentaje', 9, 3)->nullable();
            $table->string('inventariable', 1)->nullable();
            $table->string('cod_unidad', 3)->nullable();
            $table->string('kit', 1)->nullable();
            $table->decimal('unidades_venta', 19, 6)->nullable();
            $table->decimal('precio_venta_base', 19, 6)->nullable();
            $table->decimal('precio_venta_publico', 19, 6)->nullable();
            $table->string('estado_venta', 2)->nullable();
            $table->decimal('descuento_maximo', 9, 3)->nullable();
            $table->string('cod_barras', 15)->nullable();
            $table->smallInteger('cod_almacen')->nullable();
            $table->dateTime('fecha_entrega')->nullable();
            $table->string('observacion', 255)->nullable();
            $table->decimal('peso_unitario', 19, 6)->nullable();
            $table->decimal('peso_total', 19, 6)->nullable();
            $table->string('cod_tarifa', 4)->nullable();
            $table->smallInteger('version')->nullable();
            $table->string('rowguid', 36)->nullable();
            $table->timestamps();

            $table->unique(['cod_venta', 'tipo_venta', 'cod_empresa', 'cod_caja', 'linea'], 'sale_lines_pk');
            $table->index(['cod_venta', 'tipo_venta', 'cod_empresa', 'cod_caja'], 'sale_lines_header_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_sale_lines');
    }
};
