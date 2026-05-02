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
        Schema::table('erp_subfamilies', function (Blueprint $table) {
            $table->decimal('incremento', 19, 6)->nullable()->after('descripcion');
            $table->string('rowguid', 50)->nullable()->after('incremento');
        });
    }

    public function down(): void
    {
        Schema::table('erp_subfamilies', function (Blueprint $table) {
            $table->dropColumn(['incremento', 'rowguid']);
        });
    }
};
