<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_gateway_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_gateway_id');
            $table->unsignedBigInteger('payment_method_id');
            $table->string('currency', 10)->index();
            $table->decimal('deposit_fee', 13)->default(0);
            $table->decimal('withdraw_fee', 13)->default(0);
            $table->decimal('min_amount', 18)->default(0);
            $table->decimal('max_amount', 18)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payment_gateway_id')
                ->references('id')
                ->on('payment_gateways')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('payment_method_id')
                ->references('id')
                ->on('payment_methods')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_methods');
    }
};
