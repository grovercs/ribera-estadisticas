<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_subfamilies', function (Blueprint $table) {
            $table->id();
            $table->string('cod_subfamilia', 10);
            $table->string('cod_familia', 10)->index();
            $table->string('descripcion', 100)->nullable();
            $table->timestamps();

            $table->unique(['cod_subfamilia', 'cod_familia'], 'subfam_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_subfamilies');
    }
};
