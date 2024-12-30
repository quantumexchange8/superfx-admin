<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trading_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('meta_login')->unique();
            $table->unsignedBigInteger('account_type_id')->default(1);
            $table->integer('currency_digits')->nullable();
            $table->decimal('balance', 13)->nullable();
            $table->decimal('credit', 13)->nullable();
            $table->double('margin')->nullable();
            $table->double('margin_free')->nullable();
            $table->double('margin_level')->nullable();
            $table->integer('margin_leverage')->nullable();
            $table->double('profit')->nullable();
            $table->double('storage')->nullable();
            $table->double('commission')->nullable();
            $table->decimal('floating', 13)->nullable();
            $table->decimal('equity', 13)->nullable();
            $table->integer('so_activation')->nullable();
            $table->integer('so_time')->nullable();
            $table->double('so_level')->nullable();
            $table->double('so_equity')->nullable();
            $table->double('so_margin')->nullable();
            $table->double('assets')->nullable();
            $table->double('liabilities')->nullable();
            $table->double('blocked_commission')->nullable();
            $table->double('blocked_profit')->nullable();
            $table->double('margin_initial')->nullable();
            $table->double('margin_maintenance')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('account_type_id')
                ->references('id')
                ->on('account_types')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_accounts');
    }
};
