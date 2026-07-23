<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccommodationResource;
use App\Models\Accommodation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AccommodationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', Rule::in(['Mandalay', 'Yangon'])],
            'township' => ['nullable', 'string', 'max:255'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'bedrooms' => ['nullable', 'integer', 'min:1', 'max:10'],
            'type' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $userId = auth('sanctum')->id();

        $accommodations = Accommodation::query()
            ->where('status', 'approved')
            ->with(['user:id,name,email,phone,role', 'images'])
            ->withCount(['reviews' => fn ($q) => $q->where('is_hidden', false)])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('township', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($filters['city'] ?? null, fn ($query, string $city) => $query->where('city', $city))
            ->when($filters['township'] ?? null, function ($query, string $township) {
                $query->where(function ($q) use ($township) {
                    $q->where('township', $township)->orWhere('location', $township);
                });
            })
            ->when($filters['min_price'] ?? null, fn ($query, $min) => $query->where('price', '>=', $min))
            ->when($filters['max_price'] ?? null, fn ($query, $max) => $query->where('price', '<=', $max))
            ->when($filters['bedrooms'] ?? null, fn ($query, int $bedrooms) => $query->where('bedrooms', $bedrooms))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($userId, function ($query) use ($userId) {
                $query->withExists([
                    'favourites as is_favourite' => fn ($q) => $q->where('user_id', $userId),
                ]);
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 12);

        return AccommodationResource::collection($accommodations)
            ->additional([
                'message' => 'Accommodations retrieved successfully.',
            ]);
    }

    public function show(Request $request, Accommodation $accommodation): JsonResponse
    {
        if ($accommodation->status !== 'approved') {
            return response()->json([
                'message' => 'Accommodation not found.',
            ], 404);
        }

        $userId = auth('sanctum')->id();

        $accommodation->load([
            'user:id,name,email,phone,role',
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order'),
            'reviews' => fn ($query) => $query->where('is_hidden', false)->latest()->with('user:id,name,role'),
        ])->loadCount([
            'reviews' => fn ($q) => $q->where('is_hidden', false),
            'favourites',
        ]);

        if ($userId) {
            $accommodation->setAttribute(
                'is_favourite',
                $accommodation->favourites()->where('user_id', $userId)->exists()
            );
        }

        return response()->json([
            'message' => 'Accommodation retrieved successfully.',
            'data' => new AccommodationResource($accommodation),
        ]);
    }

    public function related(Accommodation $accommodation): AnonymousResourceCollection
    {
        $related = Accommodation::query()
            ->where('status', 'approved')
            ->where('id', '!=', $accommodation->id)
            ->where('city', $accommodation->city)
            ->with(['user:id,name,email,phone,role', 'images'])
            ->latest()
            ->limit(4)
            ->get();

        return AccommodationResource::collection($related)
            ->additional([
                'message' => 'Related accommodations retrieved successfully.',
            ]);
    }
}
