<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_gateway_has_banks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_gateway_id');
            $table->unsignedBigInteger('bank_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payment_gateway_id')
                ->references('id')
                ->on('payment_gateways')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('bank_id')
                ->references('id')
                ->on('banks')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_has_banks');
    }
};
