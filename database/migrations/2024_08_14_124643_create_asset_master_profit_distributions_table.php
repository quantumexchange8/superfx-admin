<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asset_master_profit_distributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_master_id')->nullable();
            $table->date('profit_distribution_date')->nullable();
            $table->double('profit_distribution_percent')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('asset_master_id')
                ->references('id')
                ->on('asset_masters')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_master_profit_distributions');
    }
};
