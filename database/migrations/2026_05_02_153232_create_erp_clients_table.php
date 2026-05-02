<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_clients', function (Blueprint $table) {
            $table->id();
            $table->integer('cod_cliente')->unique();
            $table->string('nombre_comercial', 100)->nullable();
            $table->string('razon_social', 100)->nullable();
            $table->string('cif', 15)->nullable();
            $table->string('direccion1', 100)->nullable();
            $table->string('direccion2', 100)->nullable();
            $table->string('cp', 10)->nullable();
            $table->string('poblacion', 50)->nullable();
            $table->string('provincia', 50)->nullable();
            $table->string('cod_pais', 3)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('e_mail', 100)->nullable();
            $table->string('cod_forma_liquidacion', 10)->nullable();
            $table->integer('cod_vendedor')->nullable()->index();
            $table->string('cod_tarifa', 10)->nullable();
            $table->decimal('limite_credito', 19, 6)->nullable();
            $table->dateTime('fecha_alta')->nullable();
            $table->dateTime('fecha_baja')->nullable();
            $table->string('moroso', 1)->nullable();
            $table->string('cod_tipo_cliente', 10)->nullable();
            $table->string('cod_zona', 10)->nullable();
            $table->string('cod_ruta', 10)->nullable();
            $table->string('tipo_facturacion', 1)->nullable();
            $table->string('observaciones', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_clients');
    }
};
