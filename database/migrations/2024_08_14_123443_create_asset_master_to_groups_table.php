<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asset_master_to_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_master_id');
            $table->unsignedBigInteger('group_id');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('asset_master_id')
                ->references('id')
                ->on('asset_masters')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('group_id')
                ->references('id')
                ->on('groups')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_master_to_groups');
    }
};
