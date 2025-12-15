<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->string('airline_code', 3);
            $table->string('number', 10);
            $table->string('departure_airport_code', 5);
            $table->string('arrival_airport_code', 5);
            $table->time('departure_time');
            $table->time('arrival_time');
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->unique(['airline_code', 'number']);

            $table->foreign('airline_code')->references('code')->on('airlines')->cascadeOnDelete();
            $table->foreign('departure_airport_code')->references('code')->on('airports')->cascadeOnDelete();
            $table->foreign('arrival_airport_code')->references('code')->on('airports')->cascadeOnDelete();

            $table->index(['departure_airport_code', 'arrival_airport_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
