<?php

namespace Database\Seeders;

use App\Models\Collection;
use Illuminate\Database\Seeder;

class CollectionSeeder extends Seeder
{
    public function run(): void
    {
        $collections = [
            'Summer 2024',
            'Winter Collection',
            'Spring Special'
        ];

        foreach ($collections as $collection) {
            Collection::create([
                'name' => $collection,
                'description' => $collection . ' Collection',
                'is_active' => true
            ]);
        }
    }
}
