<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityPostResource;
use App\Models\CommunityPost;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CommunityPostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'city' => ['nullable', Rule::in(['Yangon', 'Mandalay'])],
            'post_type' => ['nullable', Rule::in(['looking_for_accommodation', 'accommodation_available'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $posts = CommunityPost::query()
            ->with('user:id,name,email,phone,role')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['city'] ?? null, fn ($q, $city) => $q->where('city', $city))
            ->when($filters['post_type'] ?? null, fn ($q, $type) => $q->where('post_type', $type))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('township', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        return CommunityPostResource::collection($posts)
            ->additional(['message' => 'Community posts retrieved successfully.']);
    }

    public function show(CommunityPost $post): JsonResponse
    {
        $post->load('user:id,name,email,phone,role');

        return response()->json([
            'message' => 'Community post retrieved successfully.',
            'data' => new CommunityPostResource($post),
        ]);
    }

    public function approve(CommunityPost $post): JsonResponse
    {
        if ($post->status === 'approved') {
            return response()->json([
                'message' => 'Post is already approved.',
            ], 422);
        }

        $post->update(['status' => 'approved']);
        $post->load('user:id,name,email,phone,role');

        UserNotification::query()->create([
            'user_id' => $post->user_id,
            'type' => 'community_post_approved',
            'title' => 'Your post was approved',
            'message' => "Good news! Your community post \"{$post->title}\" has been approved and is now live.",
            'data' => [
                'post_id' => $post->id,
                'status' => 'approved',
            ],
        ]);

        return response()->json([
            'message' => 'Community post approved successfully.',
            'data' => new CommunityPostResource($post),
        ]);
    }

    public function reject(CommunityPost $post): JsonResponse
    {
        if ($post->status === 'rejected') {
            return response()->json([
                'message' => 'Post is already rejected.',
            ], 422);
        }

        $post->update(['status' => 'rejected']);
        $post->load('user:id,name,email,phone,role');

        UserNotification::query()->create([
            'user_id' => $post->user_id,
            'type' => 'community_post_rejected',
            'title' => 'Your post was rejected',
            'message' => "Your community post \"{$post->title}\" was not approved. You can edit and resubmit it.",
            'data' => [
                'post_id' => $post->id,
                'status' => 'rejected',
            ],
        ]);

        return response()->json([
            'message' => 'Community post rejected successfully.',
            'data' => new CommunityPostResource($post),
        ]);
    }

    public function destroy(CommunityPost $post): JsonResponse
    {
        $post->delete();

        return response()->json([
            'message' => 'Community post deleted successfully.',
        ]);
    }
}
