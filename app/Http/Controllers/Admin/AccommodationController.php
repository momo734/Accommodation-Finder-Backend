<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccommodationIndexRequest;
use App\Http\Requests\Admin\StoreAccommodationRequest;
use App\Http\Requests\Admin\UpdateAccommodationRequest;
use App\Http\Resources\AccommodationResource;
use App\Models\Accommodation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccommodationController extends Controller
{
    public function index(AccommodationIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $accommodations = Accommodation::query()
            ->with(['user:id,name,email,phone,role', 'images'])
            ->withCount(['reviews', 'favourites', 'reports'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            })
            ->when($filters['city'] ?? null, fn ($query, string $city) => $query->where('city', $city))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        return AccommodationResource::collection($accommodations)
            ->additional([
                'message' => 'Accommodations retrieved successfully.',
            ]);
    }

    public function store(StoreAccommodationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $owner = null;

        if (! empty($data['user_id'])) {
            $owner = User::query()
                ->whereKey($data['user_id'])
                ->whereIn('role', ['owner', 'user'])
                ->first();
        }

        if (! $owner && ! empty($data['posted_by'])) {
            $owner = User::query()
                ->whereIn('role', ['owner', 'user'])
                ->where('name', $data['posted_by'])
                ->first();
        }

        if (! $owner) {
            $role = $data['poster_role'] ?? 'owner';
            $owner = User::query()
                ->where('role', $role)
                ->where('is_active', true)
                ->latest()
                ->first();
        }

        if (! $owner) {
            return response()->json([
                'message' => 'No owner or user account available to assign this listing.',
            ], 422);
        }

        $accommodation = Accommodation::query()->create([
            'user_id' => $owner->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'city' => $data['city'],
            'location' => $data['location'],
            'township' => $data['township'] ?? $data['location'],
            'address' => $data['address'] ?? $data['location'],
            'price' => $data['price'],
            'type' => $data['type'] ?? 'Apartment',
            'bedrooms' => $data['bedrooms'] ?? null,
            'bathrooms' => $data['bathrooms'] ?? null,
            'furnishing' => $data['furnishing'] ?? null,
            'status' => 'pending',
        ]);

        $accommodation->load(['user:id,name,email,phone,role', 'images'])
            ->loadCount(['reviews', 'favourites', 'reports']);

        return response()->json([
            'message' => 'Accommodation created successfully.',
            'data' => new AccommodationResource($accommodation),
        ], 201);
    }

    public function show(Accommodation $accommodation): JsonResponse
    {
        $accommodation->load([
            'user:id,name,email,phone,role',
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order'),
            'reviews.user:id,name,email,role',
        ])->loadCount(['reviews', 'favourites', 'reports']);

        return response()->json([
            'message' => 'Accommodation retrieved successfully.',
            'data' => new AccommodationResource($accommodation),
        ]);
    }

    public function update(UpdateAccommodationRequest $request, Accommodation $accommodation): JsonResponse
    {
        $accommodation->update($request->validated());

        $accommodation->load(['user:id,name,email,phone,role', 'images'])
            ->loadCount(['reviews', 'favourites', 'reports']);

        return response()->json([
            'message' => 'Accommodation updated successfully.',
            'data' => new AccommodationResource($accommodation),
        ]);
    }

    public function approve(Accommodation $accommodation): JsonResponse
    {
        if ($accommodation->status === 'approved') {
            return response()->json([
                'message' => 'Accommodation is already approved.',
            ], 422);
        }

        $accommodation->update(['status' => 'approved']);

        $accommodation->load(['user:id,name,email,phone,role', 'images'])
            ->loadCount(['reviews', 'favourites', 'reports']);

        return response()->json([
            'message' => 'Accommodation approved successfully.',
            'data' => new AccommodationResource($accommodation),
        ]);
    }

    public function reject(Accommodation $accommodation): JsonResponse
    {
        if ($accommodation->status === 'rejected') {
            return response()->json([
                'message' => 'Accommodation is already rejected.',
            ], 422);
        }

        $accommodation->update(['status' => 'rejected']);

        $accommodation->load(['user:id,name,email,phone,role', 'images'])
            ->loadCount(['reviews', 'favourites', 'reports']);

        return response()->json([
            'message' => 'Accommodation rejected successfully.',
            'data' => new AccommodationResource($accommodation),
        ]);
    }

    public function destroy(Accommodation $accommodation): JsonResponse
    {
        $accommodation->delete();

        return response()->json([
            'message' => 'Accommodation deleted successfully.',
        ]);
    }
}
