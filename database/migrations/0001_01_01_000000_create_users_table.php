<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('dial_code', 50)->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('phone_number')->nullable()->unique();
            $table->date('dob')->nullable();
            $table->string('address')->nullable();
            $table->mediumInteger('city_id')->nullable();
            $table->mediumInteger('state_id')->nullable();
            $table->mediumInteger('country_id')->nullable();
            $table->string('nationality')->nullable();
            $table->ipAddress('register_ip')->nullable()->default('::1');
            $table->ipAddress('last_login_ip')->nullable()->default('::1');
            $table->unsignedBigInteger('upline_id')->nullable();
            $table->string('hierarchyList')->nullable();
            $table->string('referral_code')->nullable();
            $table->string('id_number')->nullable();
            $table->string('kyc_status')->default('approved');
            $table->timestamp('kyc_approved_at')->nullable();
            $table->text('kyc_approval_description')->nullable();
            $table->string('gender')->nullable();
            $table->integer('ct_user_id')->nullable();
            $table->string('role')->default('member');
            $table->string('status')->default('active');
            $table->string('remarks')->nullable();
            $table->double('rebate_amount')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
