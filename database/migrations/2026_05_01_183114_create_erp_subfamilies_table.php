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
        Schema::create('erp_subfamilies', function (Blueprint $table) {
            $table->id();
            $table->string('cod_familia', 4);
            $table->string('cod_subfamilia', 4);
            $table->string('descripcion', 40)->nullable();
            $table->decimal('incremento', 9, 3)->nullable();
            $table->string('rowguid', 36)->nullable();
            $table->timestamps();

            $table->unique(['cod_familia', 'cod_subfamilia']);
            $table->index('cod_familia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_subfamilies');
    }
};
