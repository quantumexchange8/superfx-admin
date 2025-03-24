<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('markup_profile_to_account_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('markup_profile_id');
            $table->unsignedBigInteger('account_type_id');
            $table->timestamps();

            $table->foreign('markup_profile_id')->references('id')->on('markup_profiles')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('account_type_id')->references('id')->on('account_types')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('markup_profile_to_account_type');
    }
};
