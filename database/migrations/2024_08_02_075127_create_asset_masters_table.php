<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asset_masters', function (Blueprint $table) {
            $table->id();
            $table->string('asset_name')->nullable();
            $table->string('trader_name')->nullable();
            $table->string('category')->nullable();
            $table->string('type')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->integer('total_investors')->nullable();
            $table->decimal('total_fund', 13)->nullable();
            $table->decimal('minimum_investment')->nullable();
            $table->integer('minimum_investment_period')->nullable();
            $table->decimal('performance_fee')->nullable();
            $table->decimal('total_gain')->nullable();
            $table->decimal('monthly_gain')->nullable();
            $table->decimal('latest_profit')->nullable();
            $table->string('profit_generation_mode')->nullable();
            $table->double('expected_gain_profit')->nullable();
            $table->string('status', 50)->nullable();
            $table->integer('total_likes_count')->nullable()->default(0);
            $table->unsignedBigInteger('edited_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('edited_by')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_masters');
    }
};
