<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rebate_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('account_type_id')->nullable();
            $table->unsignedBigInteger('symbol_group_id')->nullable();
            $table->decimal('amount')->nullable()->default(0);
            $table->unsignedBigInteger('edited_by')->nullable();
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

            $table->foreign('symbol_group_id')
                ->references('id')
                ->on('symbol_groups')
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
        Schema::dropIfExists('rebate_allocations');
    }
};
