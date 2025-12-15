<?php

namespace Database\Seeders;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/sample.json');
        $raw = file_get_contents($path);
        $data = json_decode($raw, true);

        foreach ($data['airlines'] ?? [] as $row) {
            Airline::updateOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name']]
            );
        }

        foreach ($data['airports'] ?? [] as $row) {
            Airport::updateOrCreate(
                ['code' => $row['code']],
                [
                    'city_code' => $row['city_code'],
                    'name' => $row['name'],
                    'city' => $row['city'],
                    'country_code' => $row['country_code'],
                    'region_code' => $row['region_code'] ?? null,
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                    'timezone' => $row['timezone'],
                ]
            );
        }

        foreach ($data['flights'] ?? [] as $row) {
            Flight::updateOrCreate(
                ['airline_code' => $row['airline'], 'number' => $row['number']],
                [
                    'departure_airport_code' => $row['departure_airport'],
                    'arrival_airport_code' => $row['arrival_airport'],
                    'departure_time' => $row['departure_time'],
                    'arrival_time' => $row['arrival_time'],
                    'price' => $row['price'],
                ]
            );
        }
    }
}
