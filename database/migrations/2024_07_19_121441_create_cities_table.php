<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->mediumInteger('state_id')->unsigned();
            $table->string('state_code', 255)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->mediumInteger('country_id')->unsigned();
            $table->char('country_code', 2)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('created_at')->default('2014-01-01 06:31:01');
            $table->timestamp('updated_at')->default(now());
            $table->tinyInteger('flag')->default(1);
            $table->string('wikiDataId', 255)->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->nullable()->comment('Rapid API GeoDB Cities');

            $table->index('state_id', 'cities_test_ibfk_1');
            $table->index('country_id', 'cities_test_ibfk_2');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
