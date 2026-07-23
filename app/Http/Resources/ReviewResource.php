<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'is_hidden' => $this->is_hidden,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'role' => $this->user->role,
                ];
            }),
            'date' => optional($this->created_at)?->toDateString(),
            'accommodation' => $this->whenLoaded('accommodation', function () {
                return [
                    'id' => $this->accommodation->id,
                    'title' => $this->accommodation->title,
                    'city' => $this->accommodation->city,
                    'location' => $this->accommodation->location,
                    'status' => $this->accommodation->status,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
