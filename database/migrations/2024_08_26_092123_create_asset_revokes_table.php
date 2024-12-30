<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetRevokesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asset_revokes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('asset_subscription_id');
            $table->unsignedBigInteger('asset_master_id');
            $table->unsignedInteger('meta_login')->nullable();
            $table->decimal('balance_on_revoke', 13, 2)->nullable();
            $table->decimal('penalty_percentage', 13, 2)->nullable();
            $table->decimal('penalty_fee', 13, 2)->nullable();
            $table->timestamp('approval_at')->nullable();
            $table->string('status')->default('pending');
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('handle_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('asset_subscription_id')
                ->references('id')
                ->on('asset_subscriptions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('asset_master_id')
                ->references('id')
                ->on('asset_masters')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('handle_by')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asset_revokes');
    }
}
