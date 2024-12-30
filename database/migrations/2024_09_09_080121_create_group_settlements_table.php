<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->timestamp('transaction_start_at')->nullable();
            $table->timestamp('transaction_end_at')->nullable();
            $table->decimal('group_deposit', 13)->nullable();
            $table->decimal('group_withdrawal', 13)->nullable();
            $table->decimal('group_fee_percentage')->nullable();
            $table->decimal('group_fee', 13)->nullable();
            $table->decimal('group_balance', 13)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('group_id')
                ->references('id')
                ->on('groups')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_settlements');
    }
};
