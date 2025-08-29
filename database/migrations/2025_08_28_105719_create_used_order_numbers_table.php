<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('used_order_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('type', 100);
            $table->string('order_number');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['type', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('used_order_numbers');
    }
};
