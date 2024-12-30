<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asset_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('meta_login')->nullable();
            $table->unsignedBigInteger('asset_master_id')->nullable();
            $table->decimal('investment_amount', 13)->nullable()->default(0);
            $table->decimal('top_up_amount', 13)->nullable()->default(0);
            $table->integer('investment_periods')->nullable();
            $table->string('status')->nullable()->default('ongoing');
            $table->double('cumulative_profit_distributions')->nullable();
            $table->double('cumulative_earnings')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamp('matured_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_subscriptions');
    }
};
