<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billboard_bonuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('billboard_profile_id')->nullable();
            $table->decimal('target_amount', 13)->nullable();
            $table->decimal('achieved_percentage')->nullable();
            $table->decimal('achieved_amount', 13)->nullable();
            $table->decimal('bonus_rate')->nullable();
            $table->decimal('bonus_amount')->nullable();
            $table->integer('bonus_month')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('billboard_profile_id')
                ->references('id')
                ->on('billboard_profiles')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billboard_bonuses');
    }
};
