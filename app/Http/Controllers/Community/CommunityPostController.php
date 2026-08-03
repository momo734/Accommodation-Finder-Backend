<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityPostRequest;
use App\Http\Requests\Community\UpdateCommunityPostRequest;
use App\Http\Resources\AccommodationResource;
use App\Http\Resources\CommunityPostResource;
use App\Models\Accommodation;
use App\Models\CommunityPost;
use App\Models\CommunityPostImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CommunityPostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', Rule::in(['Yangon', 'Mandalay'])],
            'township' => ['nullable', 'string', 'max:255'],
            'post_type' => ['nullable', Rule::in(['looking_for_accommodation', 'accommodation_available'])],
            'mine' => ['nullable'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = auth('sanctum')->user();
        $mine = filter_var($request->input('mine', false), FILTER_VALIDATE_BOOLEAN);

        $posts = CommunityPost::query()
            ->with([
                'user:id,name,email,phone,role',
                'images:id,community_post_id,image_path,is_primary,sort_order',
            ])
            ->withCount(['likes', 'comments', 'favourites'])
            ->when(
                $mine && $user,
                fn ($q) => $q->where('user_id', $user->id),
                fn ($q) => $q->where('status', 'approved')
            )
            ->when($user, function ($query) use ($user) {
                $query->withExists([
                    'likes as liked_by_user' => fn ($q) => $q->where('user_id', $user->id),
                    'favourites as favourited_by_user' => fn ($q) => $q->where('user_id', $user->id),
                ]);
            })
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('township', 'like', "%{$search}%");
                });
            })
            ->when($filters['city'] ?? null, fn ($q, $city) => $q->where('city', $city))
            ->when($filters['township'] ?? null, fn ($q, $township) => $q->where('township', $township))
            ->when($filters['post_type'] ?? null, fn ($q, $type) => $q->where('post_type', $type))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 12);

        return CommunityPostResource::collection($posts)
            ->additional(['message' => 'Community posts retrieved successfully.']);
    }

    /** Authenticated: all of the current user's posts, including pending/rejected. */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        $posts = CommunityPost::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'user:id,name,email,phone,role',
                'images:id,community_post_id,image_path,is_primary,sort_order',
            ])
            ->withCount(['likes', 'comments', 'favourites'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END")
            ->latest('id')
            ->paginate($filters['per_page'] ?? 50);

        return CommunityPostResource::collection($posts)
            ->additional(['message' => 'Your posts retrieved successfully.']);
    }

    public function store(StoreCommunityPostRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['image'], $data['images'], $data['image_path']);

        $post = CommunityPost::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        $this->syncGallery($post, $this->collectImageFiles($request), replace: false);

        $post->load(['user:id,name,email,phone,role', 'images']);

        return response()->json([
            'message' => 'Post submitted for admin approval.',
            'data' => new CommunityPostResource($post),
        ], 201);
    }

    public function show(Request $request, CommunityPost $post): JsonResponse
    {
        $user = auth('sanctum')->user();
        $isOwner = $user && $user->id === $post->user_id;

        if ($post->status !== 'approved' && ! $isOwner) {
            return response()->json([
                'message' => 'Post not found.',
            ], 404);
        }

        $post->load(['user:id,name,email,phone,role', 'images'])
            ->loadCount(['likes', 'comments', 'favourites']);

        if ($user) {
            $post->setAttribute(
                'liked_by_user',
                $post->likes()->where('user_id', $user->id)->exists()
            );
            $post->setAttribute(
                'favourited_by_user',
                $post->favourites()->where('user_id', $user->id)->exists()
            );
        }

        return response()->json([
            'message' => 'Post retrieved successfully.',
            'data' => new CommunityPostResource($post),
        ]);
    }

    public function related(CommunityPost $post): JsonResponse
    {
        if ($post->status !== 'approved') {
            return response()->json([
                'message' => 'Post not found.',
            ], 404);
        }

        $relatedPosts = CommunityPost::query()
            ->where('status', 'approved')
            ->where('post_type', 'accommodation_available')
            ->where('id', '!=', $post->id)
            ->where('city', $post->city)
            ->with(['user:id,name,email,phone,role', 'images'])
            ->withCount(['likes', 'comments', 'favourites'])
            ->latest()
            ->limit(4)
            ->get();

        $listings = Accommodation::query()
            ->where('status', 'approved')
            ->where('city', $post->city)
            ->with(['user:id,name,email,phone,role', 'images'])
            ->latest()
            ->limit(4)
            ->get();

        return response()->json([
            'message' => 'Related hostels retrieved successfully.',
            'data' => [
                'posts' => CommunityPostResource::collection($relatedPosts)->resolve(),
                'listings' => AccommodationResource::collection($listings)->resolve(),
            ],
        ]);
    }

    public function update(UpdateCommunityPostRequest $request, CommunityPost $post): JsonResponse
    {
        $data = $request->validated();
        unset($data['image'], $data['images'], $data['image_path']);

        $files = $this->collectImageFiles($request);
        if ($files !== []) {
            $this->syncGallery($post, $files, replace: true);
        }

        $data['status'] = 'pending';
        $post->update($data);
        $post->load(['user:id,name,email,phone,role', 'images']);

        return response()->json([
            'message' => 'Post updated and resubmitted for admin approval.',
            'data' => new CommunityPostResource($post),
        ]);
    }

    public function destroy(Request $request, CommunityPost $post): JsonResponse
    {
        if ($request->user()->id !== $post->user_id) {
            return response()->json([
                'message' => 'You can only delete your own posts.',
            ], 403);
        }

        foreach ($post->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        if ($post->image_path) {
            Storage::disk('public')->delete($post->image_path);
        }

        $post->delete();

        return response()->json([
            'message' => 'Post deleted successfully.',
        ]);
    }

    /**
     * @return list<UploadedFile>
     */
    private function collectImageFiles(Request $request): array
    {
        $files = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                if ($file instanceof UploadedFile) {
                    $files[] = $file;
                }
            }
        }

        if ($files === [] && $request->hasFile('image')) {
            $file = $request->file('image');
            if ($file instanceof UploadedFile) {
                $files[] = $file;
            }
        }

        return array_slice($files, 0, 6);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function syncGallery(CommunityPost $post, array $files, bool $replace): void
    {
        if ($files === []) {
            return;
        }

        if ($replace) {
            foreach ($post->images()->get() as $image) {
                Storage::disk('public')->delete($image->image_path);
                $image->delete();
            }
            if ($post->image_path) {
                Storage::disk('public')->delete($post->image_path);
            }
        }

        $paths = [];
        foreach ($files as $file) {
            $paths[] = $file->store('community-posts', 'public');
        }

        $post->update(['image_path' => $paths[0]]);

        foreach ($paths as $index => $path) {
            CommunityPostImage::query()->create([
                'community_post_id' => $post->id,
                'image_path' => $path,
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ]);
        }
    }
}
