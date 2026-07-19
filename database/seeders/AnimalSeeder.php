<?php

namespace Database\Seeders;

use App\Models\Animal;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AnimalSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $animals = [
            'Macaw' => ['Demon', 'Angel', 'Ricco', 'Pilot'],
            'Chinchilla' => ['Lily', 'Fidget'],
            'Degu' => ['Daisy', 'Pansy', 'Cerys', 'Tulip'],
            // Placeholder names — replace with the real ones in the Hub.
            'Guinea Pig' => ['Guinea Pig 1', 'Guinea Pig 2', 'Guinea Pig 3', 'Guinea Pig 4', 'Guinea Pig 5'],
            'Rabbit' => ['Rabbit 1', 'Rabbit 2', 'Rabbit 3', 'Rabbit 4', 'Rabbit 5'],
            'Chicken' => ['Chicken 1', 'Chicken 2', 'Chicken 3'],
        ];

        foreach ($animals as $species => $names) {
            foreach ($names as $name) {
                Animal::firstOrCreate(
                    ['name' => $name, 'species' => $species],
                    ['status' => 'active', 'welfare_status' => 'green'],
                );
            }
        }
    }
}
