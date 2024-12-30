<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('symbols', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('symbol_group_id')->nullable();
            $table->string('symbol_group_name')->nullable();
            $table->string('meta_symbol_name')->nullable();
            $table->string('meta_path')->nullable();
            $table->string('meta_digits')->nullable();
            $table->string('meta_contract_size')->nullable();
            $table->decimal('meta_swap_long')->nullable();
            $table->decimal('meta_swap_short')->nullable();
            $table->string('meta_swap_3_day')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('symbol_group_id')
                ->references('id')
                ->on('symbol_groups')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('symbols');
    }
};
