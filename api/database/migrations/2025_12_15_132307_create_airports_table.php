<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airports', function (Blueprint $table) {
            $table->string('code', 5)->primary();
            $table->string('city_code', 5)->index();
            $table->string('name', 180);
            $table->string('city', 120);
            $table->string('country_code', 2)->index();
            $table->string('region_code', 10)->nullable()->index();
            $table->decimal('latitude', 10, 6);
            $table->decimal('longitude', 10, 6);
            $table->string('timezone', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airports');
    }
};
