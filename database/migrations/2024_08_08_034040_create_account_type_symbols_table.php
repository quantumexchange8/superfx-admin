<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('account_type_symbols', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_type_id');
            $table->unsignedBigInteger('symbol_group_id');
            $table->string('symbol_group_name');
            $table->unsignedBigInteger('symbol_id');
            $table->string('meta_symbol_name');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('account_type_id')
                ->references('id')
                ->on('account_types')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('symbol_group_id')
                ->references('id')
                ->on('symbol_groups')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('symbol_id')
                ->references('id')
                ->on('symbols')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_type_symbols');
    }
};
