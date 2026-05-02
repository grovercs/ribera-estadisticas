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
        Schema::create('erp_clients', function (Blueprint $table) {
            $table->id();
            $table->integer('cod_cliente')->unique();
            $table->string('nombre_comercial', 40)->nullable();
            $table->string('razon_social', 40)->nullable();
            $table->string('cif', 15)->nullable();
            $table->string('direccion1', 40)->nullable();
            $table->string('direccion2', 40)->nullable();
            $table->string('cp', 8)->nullable();
            $table->string('poblacion', 40)->nullable();
            $table->string('provincia', 40)->nullable();
            $table->string('cod_pais', 2)->nullable();
            $table->string('cod_idioma', 2)->nullable();
            $table->string('cod_divisa', 3)->nullable();
            $table->string('cod_tipo_cliente', 2)->nullable();
            $table->string('telefono', 15)->nullable();
            $table->string('fax', 15)->nullable();
            $table->string('e_mail', 40)->nullable();
            $table->string('cod_forma_liquidacion', 4)->nullable();
            $table->smallInteger('dia_pago1')->nullable();
            $table->smallInteger('dia_pago2')->nullable();
            $table->smallInteger('dia_pago3')->nullable();
            $table->decimal('limite_credito', 19, 6)->nullable();
            $table->string('moroso', 1)->nullable();
            $table->integer('cod_banco')->nullable();
            $table->string('direccion_banco1', 40)->nullable();
            $table->string('cp_banco', 8)->nullable();
            $table->string('poblacion_banco', 40)->nullable();
            $table->string('ccc', 23)->nullable();
            $table->string('iban', 40)->nullable();
            $table->string('swift', 11)->nullable();
            $table->decimal('dto_pronto_pago', 9, 3)->nullable();
            $table->smallInteger('plazo_entrega')->nullable();
            $table->integer('cod_vendedor')->nullable()->index();
            $table->decimal('comision', 19, 6)->nullable();
            $table->dateTime('fecha_alta')->nullable();
            $table->dateTime('fecha_baja')->nullable();
            $table->integer('cod_zona')->nullable();
            $table->integer('cod_estado_cliente')->nullable();
            $table->integer('cod_ruta')->nullable();
            $table->string('tipo_operacion_iva', 16)->nullable();
            $table->string('ventas_credito_contado', 10)->nullable();
            $table->string('iva_incluido_ventas', 1)->nullable();
            $table->string('latitud', 15)->nullable();
            $table->string('longitud', 15)->nullable();
            $table->string('rowguid', 36)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_clients');
    }
};
