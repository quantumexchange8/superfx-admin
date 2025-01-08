<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('currency_conversion_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency')->nullable();
            $table->string('crypto_network')->nullable();
            $table->string('target_currency')->nullable();
            $table->decimal('deposit_rate')->nullable();
            $table->decimal('withdrawal_rate')->nullable();
            $table->string('deposit_charge_type')->nullable();
            $table->decimal('deposit_charge_amount')->nullable();
            $table->string('withdrawal_charge_type')->nullable();
            $table->decimal('withdrawal_charge_amount')->nullable();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_conversion_rates');
    }
};
