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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('tax_id')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->enum('type', ['individual', 'business'])->default('business');
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->integer('order_count')->default(0);
            $table->date('last_order_date')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
