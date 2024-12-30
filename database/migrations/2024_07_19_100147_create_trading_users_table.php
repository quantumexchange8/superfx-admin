<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trading_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('meta_login')->unique();
            $table->unsignedBigInteger('account_type_id')->default(1);
            $table->string('meta_group')->nullable();
            $table->string('name')->nullable();
            $table->string('country')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_password')->nullable();
            $table->unsignedInteger('leverage')->nullable();
            $table->string('main_password')->nullable();
            $table->string('invest_password')->nullable();
            $table->integer('cert_serial_number')->nullable();
            $table->integer('rights')->nullable();
            $table->string('mq_id')->nullable(); //MetaQuotes ID of the user
            $table->string('registration')->nullable();
            $table->string('last_access')->nullable();
            $table->string('last_pass_change')->nullable();
            $table->string('last_ip')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();

            //unconfirmed
            $table->string('company')->nullable();

            $table->string('account')->nullable();
            $table->integer('language')->nullable();
            $table->integer('client_id')->nullable();
            $table->string('meta_id')->nullable();
            $table->string('status')->nullable();
            $table->string('comment')->nullable();
            $table->unsignedInteger('color')->nullable();
            $table->integer('agent')->nullable();
            $table->decimal('balance', 11, 2)->nullable();
            $table->decimal('credit', 11, 2)->nullable();
            $table->decimal('interest_rate', 11, 2)->nullable();
            $table->decimal('commission_daily', 11, 2)->nullable();
            $table->decimal('commission_monthly', 11, 2)->nullable();
            $table->float('balance_prev_day')->nullable();
            $table->float('balance_prev_month')->nullable();
            $table->float('equity_prev_day')->nullable();
            $table->float('equity_prev_month')->nullable();
            $table->string('trade_accounts')->nullable(); //User account numbers in external trading systems as [gateway ID]=[account number in the system to which the gateway is connected].

            //extra
            $table->string('trade_accounts_currency')->nullable();
            $table->string('trade_accounts_platform')->nullable();
            $table->string('trade_accounts_type')->nullable();

            $table->string('lead_campaign')->nullable();
            $table->string('lead_source')->nullable();

            //additional
            $table->string('remarks')->nullable();
            $table->boolean('allow_trade')->default(true);
            $table->boolean('allow_change_pass')->default(true);
            $table->string('module')->default('trading');
            $table->string('category')->nullable();
            $table->string('acc_status')->default('active');
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
        Schema::dropIfExists('trading_users');
    }
};
