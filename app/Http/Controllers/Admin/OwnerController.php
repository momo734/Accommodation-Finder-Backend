<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OwnerIndexRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OwnerController extends Controller
{
    public function index(OwnerIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $owners = User::query()
            ->where('role', 'owner')
            ->withCount(['accommodations', 'reviews', 'favourites', 'reports'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        return UserResource::collection($owners)
            ->additional([
                'message' => 'Owners retrieved successfully.',
            ]);
    }

    public function show(User $owner): JsonResponse
    {
        if ($response = $this->ensureOwner($owner)) {
            return $response;
        }

        $owner->loadCount(['accommodations', 'reviews', 'favourites', 'reports']);

        return response()->json([
            'message' => 'Owner retrieved successfully.',
            'data' => new UserResource($owner),
        ]);
    }

    public function suspend(User $owner): JsonResponse
    {
        if ($response = $this->ensureOwner($owner)) {
            return $response;
        }

        if (! $owner->is_active) {
            return response()->json([
                'message' => 'Owner is already suspended.',
            ], 422);
        }

        $owner->update(['is_active' => false]);
        $owner->tokens()->delete();
        $owner->loadCount(['accommodations', 'reviews', 'favourites', 'reports']);

        return response()->json([
            'message' => 'Owner suspended successfully.',
            'data' => new UserResource($owner),
        ]);
    }

    public function activate(User $owner): JsonResponse
    {
        if ($response = $this->ensureOwner($owner)) {
            return $response;
        }

        if ($owner->is_active) {
            return response()->json([
                'message' => 'Owner is already active.',
            ], 422);
        }

        $owner->update(['is_active' => true]);
        $owner->loadCount(['accommodations', 'reviews', 'favourites', 'reports']);

        return response()->json([
            'message' => 'Owner activated successfully.',
            'data' => new UserResource($owner),
        ]);
    }

    public function destroy(User $owner): JsonResponse
    {
        if ($response = $this->ensureOwner($owner)) {
            return $response;
        }

        $owner->tokens()->delete();
        $owner->delete();

        return response()->json([
            'message' => 'Owner deleted successfully.',
        ]);
    }

    private function ensureOwner(User $owner): ?JsonResponse
    {
        if ($owner->role !== 'owner') {
            return response()->json([
                'message' => 'Owner not found.',
            ], 404);
        }

        return null;
    }
}
