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
        Schema::table('erp_families', function (Blueprint $table) {
            $table->decimal('codigo_contable_compras', 19, 6)->nullable()->after('descripcion');
            $table->decimal('codigo_contable_ventas', 19, 6)->nullable()->after('codigo_contable_compras');
            $table->string('rowguid', 50)->nullable()->after('codigo_contable_ventas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('erp_families', function (Blueprint $table) {
            $table->dropColumn(['codigo_contable_compras', 'codigo_contable_ventas', 'rowguid']);
        });
    }
};
