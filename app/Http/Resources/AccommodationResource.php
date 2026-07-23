<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccommodationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $images = $this->whenLoaded('images', function () {
            return $this->images
                ->sortBy([
                    ['is_primary', 'desc'],
                    ['sort_order', 'asc'],
                ])
                ->values()
                ->map(fn ($image) => [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'is_primary' => $image->is_primary,
                    'sort_order' => $image->sort_order,
                ]);
        });

        $primaryImage = null;
        if ($this->relationLoaded('images')) {
            $primaryImage = optional(
                $this->images->firstWhere('is_primary', true) ?? $this->images->first()
            )->image_path;
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'city' => $this->city,
            'township' => $this->township ?? $this->location,
            'address' => $this->address,
            'location' => $this->location,
            'price' => $this->price,
            'type' => $this->type,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'furnishing' => $this->furnishing,
            'status' => $this->status,
            'image' => $primaryImage,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                    'role' => $this->user->role,
                ];
            }),
            'owner' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'phone' => $this->user->phone,
                    'role' => $this->user->role,
                ];
            }),
            'images' => $images,
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'reviews_count' => $this->whenCounted('reviews'),
            'favourites_count' => $this->whenCounted('favourites'),
            'reports_count' => $this->whenCounted('reports'),
            'is_favourite' => $this->when(isset($this->is_favourite), (bool) $this->is_favourite),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
