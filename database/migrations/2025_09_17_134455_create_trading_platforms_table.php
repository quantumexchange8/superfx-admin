<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trading_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('platform_name');
            $table->string('slug');
            $table->string('server')->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['platform_name', 'server']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_platforms');
    }
};
