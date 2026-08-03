<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $gallery = [];
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            $gallery = $this->images
                ->sortBy([
                    ['is_primary', 'desc'],
                    ['sort_order', 'asc'],
                ])
                ->values()
                ->map(fn ($image) => [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'image_url' => MediaUrl::public($image->image_path),
                    'is_primary' => (bool) $image->is_primary,
                    'sort_order' => (int) $image->sort_order,
                ])
                ->all();
        } elseif ($this->image_path) {
            $gallery = [[
                'id' => null,
                'image_path' => $this->image_path,
                'image_url' => MediaUrl::public($this->image_path),
                'is_primary' => true,
                'sort_order' => 0,
            ]];
        }

        $primary = $gallery[0]['image_url'] ?? MediaUrl::public($this->image_path);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'post_type' => $this->post_type,
            'city' => $this->city,
            'township' => $this->township,
            'budget' => $this->budget,
            'contact_phone' => $this->contact_phone,
            'image_path' => $this->image_path,
            'image_url' => $primary,
            'images' => $gallery,
            'status' => $this->status,
            'likes_count' => (int) ($this->likes_count ?? 0),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'favourites_count' => (int) ($this->favourites_count ?? 0),
            'liked_by_user' => (bool) ($this->liked_by_user ?? false),
            'favourited_by_user' => (bool) ($this->favourited_by_user ?? false),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                    'role' => $this->user->role,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
