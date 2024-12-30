<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billboard_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('sales_calculation_mode')->nullable();
            $table->string('sales_category')->nullable();
            $table->decimal('target_amount', 13)->nullable();
            $table->decimal('bonus_rate')->nullable();
            $table->decimal('bonus_calculation_threshold')->nullable();
            $table->string('calculation_period')->nullable();
            $table->timestamp('next_payout_at')->nullable();
            $table->unsignedBigInteger('edited_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('edited_by')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billboard_profiles');
    }
};
