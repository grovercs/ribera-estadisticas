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
        Schema::create('erp_invoices', function (Blueprint $table) {
            $table->id();
            $table->integer('cod_factura');
            $table->smallInteger('tipo_factura');
            $table->smallInteger('cod_empresa');
            $table->smallInteger('cod_caja');
            $table->integer('cod_cliente')->nullable()->index();
            $table->smallInteger('cod_seccion')->nullable();
            $table->string('nombre_seccion', 40)->nullable();
            $table->string('cif', 15)->nullable();
            $table->string('razon_social', 40)->nullable();
            $table->string('nombre_comercial', 40)->nullable();
            $table->string('direccion1', 40)->nullable();
            $table->string('direccion2', 40)->nullable();
            $table->string('cp', 8)->nullable();
            $table->string('poblacion', 40)->nullable();
            $table->string('provincia', 40)->nullable();
            $table->string('cod_idioma', 2)->nullable();
            $table->string('cod_pais', 2)->nullable();
            $table->string('cod_divisa', 3)->nullable();
            $table->decimal('cambio', 19, 6)->nullable();
            $table->string('impuestos_incluidos', 1)->nullable();
            $table->decimal('importe', 19, 6)->nullable();
            $table->decimal('importe_impuestos', 19, 6)->nullable();
            $table->decimal('importe_divisa', 19, 6)->nullable();
            $table->decimal('importe_divisa_impuestos', 19, 6)->nullable();
            $table->decimal('importe_cobrado', 19, 6)->nullable();
            $table->decimal('importe_divisa_cobrado', 19, 6)->nullable();
            $table->string('factura_oficial', 1)->nullable();
            $table->smallInteger('cod_almacen')->nullable()->index();
            $table->decimal('cargo_financiacion', 9, 3)->nullable();
            $table->decimal('importe_financiacion', 19, 6)->nullable();
            $table->decimal('importe_divisa_financiacion', 19, 6)->nullable();
            $table->smallInteger('tipo_factura_rectificada')->nullable();
            $table->integer('cod_factura_rectificada')->nullable();
            $table->dateTime('fecha_factura_rectificada')->nullable();
            $table->decimal('retencion_irpf', 9, 3)->nullable();
            $table->decimal('importe_retencion_irpf', 19, 6)->nullable();
            $table->decimal('importe_divisa_retencion_irpf', 19, 6)->nullable();
            $table->integer('cod_arqueo')->nullable();
            $table->integer('cod_primera_venta')->nullable();
            $table->integer('cod_ultima_venta')->nullable();
            $table->string('tipo_operacion_iva', 16)->nullable();
            $table->string('cod_proveedor_otorgado', 20)->nullable();
            $table->string('referencia_contrato_facturae', 20)->nullable();
            $table->string('rowguid', 36)->nullable();
            $table->timestamps();

            $table->unique(['cod_factura', 'tipo_factura', 'cod_empresa', 'cod_caja'], 'invoices_pk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_invoices');
    }
};
