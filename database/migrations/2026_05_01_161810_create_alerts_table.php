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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['low_stock', 'delayed_shipment', 'overdue_payment', 'high_demand']);
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->foreignId('order_id')->nullable()->constrained('orders');
            $table->foreignId('client_id')->nullable()->constrained('clients');
            $table->string('title');
            $table->text('description');
            $table->enum('severity', ['info', 'warning', 'critical']);
            $table->enum('status', ['active', 'resolved', 'dismissed'])->default('active');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
