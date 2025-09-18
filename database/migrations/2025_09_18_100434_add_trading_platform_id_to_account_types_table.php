<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('account_types', function (Blueprint $table) {
            $table->foreignId('trading_platform_id')
                ->nullable()
                ->after('member_display_name')
                ->constrained('trading_platforms')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_types', function (Blueprint $table) {
            $table->dropForeign(['trading_platform_id']);
            $table->dropColumn('trading_platform_id');
        });
    }
};
