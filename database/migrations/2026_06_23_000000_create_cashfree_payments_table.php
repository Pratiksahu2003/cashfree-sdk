<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cashfree_payments', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->string('transaction_id')->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('INR');
            $table->string('customer_id')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('status', 50)->default('ACTIVE');
            $table->string('payment_session_id')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('refund_status', 50)->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cashfree_payments');
    }
};
