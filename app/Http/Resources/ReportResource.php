<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'description' => $this->description,
            'status' => $this->status,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'role' => $this->user->role,
                ];
            }),
            'accommodation' => $this->whenLoaded('accommodation', function () {
                return [
                    'id' => $this->accommodation->id,
                    'title' => $this->accommodation->title,
                    'city' => $this->accommodation->city,
                    'location' => $this->accommodation->location
                        ? $this->accommodation->location.', '.$this->accommodation->city
                        : $this->accommodation->city,
                    'status' => $this->accommodation->status,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
