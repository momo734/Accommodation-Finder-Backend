<?php

namespace Database\Seeders;

use App\Models\Accommodation;
use App\Models\AccommodationImage;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CommunitySeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->updateOrCreate(
            ['email' => 'owner@community.mm'],
            [
                'name' => 'Aung Ko',
                'phone' => '+95 9 111 222 333',
                'password' => Hash::make('password'),
                'role' => 'owner',
                'is_active' => true,
            ]
        );

        $renter = User::query()->updateOrCreate(
            ['email' => 'user@community.mm'],
            [
                'name' => 'Thura',
                'phone' => '+95 9 123 456 789',
                'password' => Hash::make('password'),
                'role' => 'user',
                'is_active' => true,
            ]
        );

        $listings = [
            [
                'title' => 'Modern 2BR near Mandalay Palace',
                'city' => 'Mandalay',
                'township' => 'Chanayethazan',
                'address' => '26th Street, Chanayethazan',
                'location' => 'Chanayethazan',
                'price' => 850000,
                'type' => 'Apartment',
                'bedrooms' => 2,
                'bathrooms' => 2,
                'furnishing' => 'Furnished',
                'description' => 'Bright, modern two-bedroom apartment with skyline views, walking distance to Mandalay Palace. Fully furnished, high-speed internet, secure building with 24/7 concierge.',
                'image' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=800&h=560&fit=crop',
            ],
            [
                'title' => 'Cozy Studio in Bahan, Yangon',
                'city' => 'Yangon',
                'township' => 'Bahan',
                'address' => 'Inya Road, Bahan',
                'location' => 'Bahan',
                'price' => 450000,
                'type' => 'Studio',
                'bedrooms' => 1,
                'bathrooms' => 1,
                'furnishing' => 'Furnished',
                'description' => 'Compact studio ideal for students and young professionals. Close to cafes, transit and Shwedagon.',
                'image' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=800&h=560&fit=crop',
            ],
            [
                'title' => 'Serviced Apartment in Kamayut',
                'city' => 'Yangon',
                'township' => 'Kamayut',
                'address' => 'Pyay Road, Kamayut',
                'location' => 'Kamayut',
                'price' => 700000,
                'type' => 'Serviced',
                'bedrooms' => 1,
                'bathrooms' => 1,
                'furnishing' => 'Furnished',
                'description' => 'Fully serviced apartment with housekeeping, gym access and flexible lease terms in central Kamayut.',
                'image' => 'https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=800&h=560&fit=crop',
            ],
            [
                'title' => 'Riverside 2BR in Chan Mya Tharsi',
                'city' => 'Mandalay',
                'township' => 'Chan Mya Tharsi',
                'address' => 'Strand Road, Chan Mya Tharsi',
                'location' => 'Chan Mya Tharsi',
                'price' => 650000,
                'type' => 'Apartment',
                'bedrooms' => 2,
                'bathrooms' => 2,
                'furnishing' => 'Semi-furnished',
                'description' => 'Spacious riverside apartment with balcony views, parking and peaceful neighbourhood vibes.',
                'image' => 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=800&h=560&fit=crop',
            ],
        ];

        foreach ($listings as $item) {
            $image = $item['image'];
            unset($item['image']);

            $accommodation = Accommodation::query()->updateOrCreate(
                [
                    'user_id' => $owner->id,
                    'title' => $item['title'],
                ],
                array_merge($item, ['status' => 'approved'])
            );

            AccommodationImage::query()->updateOrCreate(
                [
                    'accommodation_id' => $accommodation->id,
                    'image_path' => $image,
                ],
                [
                    'is_primary' => true,
                    'sort_order' => 0,
                ]
            );
        }

        $first = Accommodation::query()->where('title', 'Modern 2BR near Mandalay Palace')->first();
        if ($first) {
            Review::query()->updateOrCreate(
                [
                    'user_id' => $renter->id,
                    'accommodation_id' => $first->id,
                ],
                [
                    'rating' => 5,
                    'comment' => 'Excellent location and very responsive owner.',
                    'is_hidden' => false,
                ]
            );
        }
    }
}
