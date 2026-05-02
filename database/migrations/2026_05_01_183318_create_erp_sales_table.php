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
        Schema::create('erp_sales', function (Blueprint $table) {
            $table->id();
            $table->integer('cod_venta');
            $table->smallInteger('tipo_venta');
            $table->smallInteger('cod_empresa');
            $table->smallInteger('cod_caja');
            $table->smallInteger('cod_almacen')->nullable()->index();
            $table->integer('cod_cliente')->nullable()->index();
            $table->string('nombre_comercial', 40)->nullable();
            $table->string('razon_social', 40)->nullable();
            $table->string('cif', 15)->nullable();
            $table->string('direccion1', 40)->nullable();
            $table->string('cp', 8)->nullable();
            $table->string('poblacion', 40)->nullable();
            $table->string('provincia', 40)->nullable();
            $table->string('cod_pais', 2)->nullable();
            $table->string('cod_divisa', 3)->nullable();
            $table->string('impuestos_incluidos', 1)->nullable();
            $table->decimal('cambio', 19, 6)->nullable();
            $table->dateTime('fecha_venta')->nullable()->index();
            $table->string('hora_venta', 5)->nullable();
            $table->string('cod_forma_liquidacion', 4)->nullable();
            $table->integer('cod_vendedor')->nullable()->index();
            $table->string('nombre_vendedor', 40)->nullable();
            $table->string('su_pedido', 20)->nullable();
            $table->decimal('importe_cobrado', 19, 6)->nullable();
            $table->decimal('importe_pendiente', 19, 6)->nullable();
            $table->string('facturado', 1)->nullable();
            $table->decimal('importe', 19, 6)->nullable();
            $table->decimal('importe_impuestos', 19, 6)->nullable();
            $table->smallInteger('estado_venta')->nullable();
            $table->string('anulada', 1)->nullable();
            $table->integer('cod_factura_asignada')->nullable();
            $table->dateTime('fecha_entrega')->nullable();
            $table->integer('cod_arqueo')->nullable();
            $table->decimal('peso_total', 19, 6)->nullable();
            $table->string('reparto', 1)->nullable();
            $table->integer('cod_ruta')->nullable();
            $table->integer('cod_transportista')->nullable();
            $table->smallInteger('bulbos')->nullable();
            $table->decimal('dto_pronto_pago', 9, 3)->nullable();
            $table->decimal('cargo_financiacion', 9, 3)->nullable();
            $table->decimal('importe_financiacion', 19, 6)->nullable();
            $table->string('historico', 1)->nullable();
            $table->string('rowguid', 36)->nullable();
            $table->timestamps();

            $table->unique(['cod_venta', 'tipo_venta', 'cod_empresa', 'cod_caja']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_sales');
    }
};
