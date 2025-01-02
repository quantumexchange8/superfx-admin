<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('platform')->nullable();
            $table->string('environment')->nullable();
            $table->string('payment_url')->nullable();
            $table->string('payment_app_name')->nullable();
            $table->string('payment_app_number')->nullable();
            $table->string('payment_app_key')->nullable();
            $table->string('secondary_key')->nullable();
            $table->string('status')->nullable()->default('active');
            $table->unsignedBigInteger('edited_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('edited_by')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
