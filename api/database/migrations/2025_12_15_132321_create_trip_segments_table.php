<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_segments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trip_id');
            $table->unsignedInteger('segment_index');
            $table->unsignedBigInteger('flight_id');
            $table->date('departure_date');

            $table->dateTime('departure_at_utc');
            $table->dateTime('arrival_at_utc');
            $table->string('departure_tz', 64);
            $table->string('arrival_tz', 64);
            $table->dateTime('departure_at_local');
            $table->dateTime('arrival_at_local');

            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->unique(['trip_id', 'segment_index']);

            $table->foreign('trip_id')->references('id')->on('trips')->cascadeOnDelete();
            $table->foreign('flight_id')->references('id')->on('flights')->cascadeOnDelete();

            $table->index(['departure_at_utc', 'arrival_at_utc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_segments');
    }
};
