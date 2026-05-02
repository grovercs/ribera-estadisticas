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
        Schema::create('erp_sellers', function (Blueprint $table) {
            $table->id();
            $table->integer('cod_vendedor')->unique();
            $table->string('nombre', 40)->nullable();
            $table->string('direccion1', 40)->nullable();
            $table->string('cp', 8)->nullable();
            $table->string('poblacion', 40)->nullable();
            $table->string('provincia', 40)->nullable();
            $table->string('telefono', 15)->nullable();
            $table->string('fax', 15)->nullable();
            $table->string('e_mail', 40)->nullable();
            $table->string('seguridad_social', 15)->nullable();
            $table->string('mutua', 40)->nullable();
            $table->decimal('comision_general', 19, 6)->nullable();
            $table->dateTime('aniversario')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('rowguid', 36)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_sellers');
    }
};
