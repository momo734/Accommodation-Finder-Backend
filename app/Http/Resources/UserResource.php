<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'accommodations_count' => $this->whenCounted('accommodations'),
            'reviews_count' => $this->whenCounted('reviews'),
            'favourites_count' => $this->whenCounted('favourites'),
            'reports_count' => $this->whenCounted('reports'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
