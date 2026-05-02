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
        Schema::create('erp_suppliers', function (Blueprint $table) {
            $table->id();
            $table->integer('cod_proveedor')->unique();
            $table->string('nombre_comercial', 40)->nullable();
            $table->string('razon_social', 40)->nullable();
            $table->string('cif', 15)->nullable();
            $table->string('codigo_contable', 9)->nullable();
            $table->string('contrapartida', 9)->nullable();
            $table->string('direccion1', 40)->nullable();
            $table->string('direccion2', 40)->nullable();
            $table->string('cod_idioma', 2)->nullable();
            $table->string('cod_divisa', 3)->nullable();
            $table->string('cp', 8)->nullable();
            $table->string('poblacion', 40)->nullable();
            $table->string('provincia', 40)->nullable();
            $table->string('cod_pais', 2)->nullable();
            $table->string('telefono', 15)->nullable();
            $table->string('fax', 15)->nullable();
            $table->string('e_mail', 40)->nullable();
            $table->string('www', 40)->nullable();
            $table->dateTime('fecha_alta')->nullable();
            $table->dateTime('fecha_baja')->nullable();
            $table->decimal('credito_otorgado', 19, 6)->nullable();
            $table->string('cod_condiciones_compra', 6)->nullable();
            $table->string('cod_forma_liquidacion', 4)->nullable();
            $table->integer('cod_banco')->nullable();
            $table->string('direccion_banco1', 40)->nullable();
            $table->string('direccion_banco2', 40)->nullable();
            $table->string('cp_banco', 8)->nullable();
            $table->string('poblacion_banco', 40)->nullable();
            $table->string('cod_pais_banco', 2)->nullable();
            $table->string('ccc', 23)->nullable();
            $table->integer('plazo_entrega')->nullable();
            $table->integer('cod_transportista')->nullable();
            $table->integer('cod_central_compras')->nullable();
            $table->string('fecha_inicio_vacaciones', 5)->nullable();
            $table->string('fecha_fin_vacaciones', 5)->nullable();
            $table->string('facturacion_centralizada', 1)->nullable();
            $table->text('observaciones')->nullable();
            $table->string('advertencia', 80)->nullable();
            $table->string('portes_pagados', 1)->nullable();
            $table->decimal('compra_minima', 19, 6)->nullable();
            $table->string('su_cliente', 10)->nullable();
            $table->integer('cliente_central_compra')->nullable();
            $table->integer('dia_pago1')->nullable();
            $table->integer('dia_pago2')->nullable();
            $table->integer('dia_pago3')->nullable();
            $table->string('dia_vencimiento_vacaciones', 2)->nullable();
            $table->string('gln_facturas', 13)->nullable();
            $table->string('gln_pedidos', 13)->nullable();
            $table->string('iban', 40)->nullable();
            $table->string('swift', 11)->nullable();
            $table->string('criterio_de_caja', 1)->nullable();
            $table->string('tipo_persona', 1)->nullable();
            $table->string('motivo_baja', 40)->nullable();
            $table->string('telefono2', 15)->nullable();
            $table->string('telefono3', 15)->nullable();
            $table->string('telefono1_comentario', 40)->nullable();
            $table->string('telefono2_comentario', 40)->nullable();
            $table->string('telefono3_comentario', 40)->nullable();
            $table->string('fax_comentario', 40)->nullable();
            $table->string('facebook', 40)->nullable();
            $table->string('twitter', 40)->nullable();
            $table->string('youtube', 40)->nullable();
            $table->string('whatsapp', 15)->nullable();
            $table->string('latitud', 15)->nullable();
            $table->string('longitud', 15)->nullable();
            $table->string('tipo_operacion_iva', 16)->nullable();
            $table->string('inversion_sujeto_pasivo', 1)->default('N');
            $table->decimal('importe_portes_pagados', 19, 6)->nullable();
            $table->integer('valoracion_albaran')->nullable();
            $table->string('rowguid', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_suppliers');
    }
};
