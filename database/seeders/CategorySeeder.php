<?php

namespace Database\Seeders;

use App\Models\Gender;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Men Categories
            [
                'gender_name' => 'Men',
                'categories' => [
                    ['name' => 'T-Shirts', 'description' => 'Men\'s T-Shirts Collection'],
                    ['name' => 'Shirts', 'description' => 'Men\'s Shirts Collection'],
                    ['name' => 'Pants', 'description' => 'Men\'s Pants Collection'],
                    ['name' => 'Shoes', 'description' => 'Men\'s Shoes Collection'],
                    ['name' => 'Accessories', 'description' => 'Men\'s Accessories Collection'],
                ]
            ],
            // Women Categories
            [
                'gender_name' => 'Women',
                'categories' => [
                    ['name' => 'Dresses', 'description' => 'Women\'s Dresses Collection'],
                    ['name' => 'Tops', 'description' => 'Women\'s Tops Collection'],
                    ['name' => 'Skirts', 'description' => 'Women\'s Skirts Collection'],
                    ['name' => 'Shoes', 'description' => 'Women\'s Shoes Collection'],
                    ['name' => 'Accessories', 'description' => 'Women\'s Accessories Collection'],
                ]
            ],
            // Unisex Categories
            [
                'gender_name' => 'Unisex',
                'categories' => [
                    ['name' => 'Hoodies', 'description' => 'Unisex Hoodies Collection'],
                    ['name' => 'Jackets', 'description' => 'Unisex Jackets Collection'],
                    ['name' => 'Sneakers', 'description' => 'Unisex Sneakers Collection'],
                    ['name' => 'Bags', 'description' => 'Unisex Bags Collection'],
                    ['name' => 'Accessories', 'description' => 'Unisex Accessories Collection'],
                    ['name' => 'T-Shirts', 'description' => 'Unisex T-Shirts Collection'],
                ]
            ],
        ];

        foreach ($categories as $genderCategory) {
            $gender = Gender::where('name', $genderCategory['gender_name'])->first();

            foreach ($genderCategory['categories'] as $category) {
                ProductCategory::updateOrCreate(
                    [
                        'gender_id' => $gender->id,
                        'name' => $category['name']
                    ],
                    [
                        'description' => $category['description'],
                        'is_active' => true
                    ]
                );
            }
        }
    }
}
