<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(UserIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $users = User::query()
            ->whereIn('role', ['owner', 'user'])
            ->withCount(['accommodations', 'reviews', 'favourites', 'reports'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        return UserResource::collection($users)
            ->additional([
                'message' => 'Users retrieved successfully.',
            ]);
    }

    public function show(User $user): JsonResponse
    {
        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Admin accounts cannot be managed here.',
            ], 403);
        }

        $user->loadCount(['accommodations', 'reviews', 'favourites', 'reports']);

        return response()->json([
            'message' => 'User retrieved successfully.',
            'data' => new UserResource($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        if ($response = $this->guardManageableUser($request, $user)) {
            return $response;
        }

        $user->update($request->validated());
        $user->loadCount(['accommodations', 'reviews', 'favourites', 'reports']);

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => new UserResource($user),
        ]);
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        if ($response = $this->guardManageableUser($request, $user)) {
            return $response;
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'User is already suspended.',
            ], 422);
        }

        $user->update(['is_active' => false]);
        $user->tokens()->delete();
        $user->loadCount(['accommodations', 'reviews', 'favourites', 'reports']);

        return response()->json([
            'message' => 'User suspended successfully.',
            'data' => new UserResource($user),
        ]);
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        if ($response = $this->guardManageableUser($request, $user)) {
            return $response;
        }

        if ($user->is_active) {
            return response()->json([
                'message' => 'User is already active.',
            ], 422);
        }

        $user->update(['is_active' => true]);
        $user->loadCount(['accommodations', 'reviews', 'favourites', 'reports']);

        return response()->json([
            'message' => 'User activated successfully.',
            'data' => new UserResource($user),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($response = $this->guardManageableUser($request, $user)) {
            return $response;
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    private function guardManageableUser(Request $request, User $user): ?JsonResponse
    {
        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Admin accounts cannot be managed here.',
            ], 403);
        }

        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot perform this action on your own account.',
            ], 403);
        }

        return null;
    }
}
