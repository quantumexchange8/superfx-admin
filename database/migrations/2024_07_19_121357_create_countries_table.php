<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('iso3', 3)->nullable();
            $table->char('numeric_code', 3)->nullable();
            $table->char('iso2', 3)->nullable();
            $table->string('phone_code', 255);
            $table->string('capital', 255)->nullable();
            $table->string('currency', 255)->nullable();
            $table->string('currency_name', 255)->nullable();
            $table->string('currency_symbol', 255)->nullable();
            $table->string('tld', 255)->nullable();
            $table->string('native', 255)->nullable();
            $table->string('region', 255)->nullable();
            $table->unsignedMediumInteger('region_id')->nullable();
            $table->string('subregion', 255)->nullable();
            $table->unsignedMediumInteger('subregion_id')->nullable();
            $table->string('nationality', 255)->nullable();
            $table->text('timezones')->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->nullable();
            $table->text('translations')->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('emoji', 191)->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->nullable();
            $table->string('emojiU', 191)->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->nullable();
            $table->timestamp('created_at')->nullable()->default(null);
            $table->timestamp('updated_at')->default(now());
            $table->tinyInteger('flag')->default(1);
            $table->string('wikiDataId', 255)->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->nullable()->comment('Rapid API GeoDB Cities');


            $table->index('region_id', 'country_continent');
            $table->index('subregion_id', 'country_subregion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
