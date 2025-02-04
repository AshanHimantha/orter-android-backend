<?php

namespace Database\Seeders;

use App\Models\Gender;
use Illuminate\Database\Seeder;

class GenderSeeder extends Seeder
{
    public function run(): void
    {
        $genders = [
            [
                'name' => 'Men',
                'description' => 'Men\'s Fashion Category',
                'is_active' => true,
            ],
            [
                'name' => 'Women',
                'description' => 'Women\'s Fashion Category',
                'is_active' => true,
            ],
            [
                'name' => 'Unisex',
                'description' => 'Unisex Fashion Category',
                'is_active' => true,
            ]
        ];

        foreach ($genders as $gender) {
            Gender::updateOrCreate(
                ['name' => $gender['name']],
                $gender
            );
        }
    }
}
