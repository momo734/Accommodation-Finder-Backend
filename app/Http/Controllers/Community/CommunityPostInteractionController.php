<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityPostResource;
use App\Models\CommunityPost;
use App\Models\CommunityPostComment;
use App\Models\CommunityPostFavourite;
use App\Models\CommunityPostLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityPostInteractionController extends Controller
{
    public function toggleLike(Request $request, CommunityPost $post): JsonResponse
    {
        if ($post->status !== 'approved') {
            return response()->json(['message' => 'Post not found.'], 404);
        }

        $existing = CommunityPostLike::query()
            ->where('user_id', $request->user()->id)
            ->where('community_post_id', $post->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            CommunityPostLike::query()->create([
                'user_id' => $request->user()->id,
                'community_post_id' => $post->id,
            ]);
            $liked = true;
        }

        return response()->json([
            'message' => $liked ? 'Post liked.' : 'Like removed.',
            'data' => [
                'liked' => $liked,
                'likes_count' => $post->likes()->count(),
            ],
        ]);
    }

    public function toggleFavourite(Request $request, CommunityPost $post): JsonResponse
    {
        if ($post->status !== 'approved') {
            return response()->json(['message' => 'Post not found.'], 404);
        }

        $existing = CommunityPostFavourite::query()
            ->where('user_id', $request->user()->id)
            ->where('community_post_id', $post->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $favourited = false;
        } else {
            CommunityPostFavourite::query()->create([
                'user_id' => $request->user()->id,
                'community_post_id' => $post->id,
            ]);
            $favourited = true;
        }

        return response()->json([
            'message' => $favourited ? 'Added to favourites.' : 'Removed from favourites.',
            'data' => [
                'favourited' => $favourited,
                'favourites_count' => $post->favourites()->count(),
            ],
        ]);
    }

    public function comments(CommunityPost $post): JsonResponse
    {
        if ($post->status !== 'approved') {
            return response()->json(['message' => 'Post not found.'], 404);
        }

        $comments = $post->comments()
            ->with('user:id,name,role')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (CommunityPostComment $comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'comment' => $comment->body,
                'rating' => $comment->rating,
                'user' => [
                    'id' => $comment->user?->id,
                    'name' => $comment->user?->name,
                    'role' => $comment->user?->role,
                ],
                'created_at' => $comment->created_at,
            ]);

        return response()->json([
            'message' => 'Comments retrieved successfully.',
            'data' => $comments,
        ]);
    }

    public function storeComment(Request $request, CommunityPost $post): JsonResponse
    {
        if ($post->status !== 'approved') {
            return response()->json(['message' => 'Post not found.'], 404);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $comment = CommunityPostComment::query()->create([
            'user_id' => $request->user()->id,
            'community_post_id' => $post->id,
            'body' => $data['body'],
            'rating' => $data['rating'] ?? null,
        ]);

        $comment->load('user:id,name,role');

        return response()->json([
            'message' => 'Comment added.',
            'data' => [
                'id' => $comment->id,
                'body' => $comment->body,
                'comment' => $comment->body,
                'rating' => $comment->rating,
                'user' => [
                    'id' => $comment->user?->id,
                    'name' => $comment->user?->name,
                    'role' => $comment->user?->role,
                ],
                'created_at' => $comment->created_at,
            ],
        ], 201);
    }

    public function destroyComment(Request $request, CommunityPost $post, CommunityPostComment $comment): JsonResponse
    {
        if ($comment->community_post_id !== $post->id) {
            return response()->json(['message' => 'Comment not found.'], 404);
        }

        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'You can only delete your own comments.'], 403);
        }

        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted.',
        ]);
    }

    public function favouriteIds(Request $request): JsonResponse
    {
        $ids = CommunityPostFavourite::query()
            ->where('user_id', $request->user()->id)
            ->pluck('community_post_id');

        return response()->json([
            'message' => 'Favourite post ids retrieved successfully.',
            'data' => ['ids' => $ids],
        ]);
    }

    public function favourites(Request $request): JsonResponse
    {
        $posts = CommunityPost::query()
            ->where('status', 'approved')
            ->whereHas('favourites', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('user:id,name,email,phone,role')
            ->withCount(['likes', 'comments', 'favourites'])
            ->latest()
            ->paginate(20);

        $posts->getCollection()->transform(function (CommunityPost $post) {
            $post->setAttribute('liked_by_user', false);
            $post->setAttribute('favourited_by_user', true);

            return $post;
        });

        return response()->json([
            'message' => 'Favourite posts retrieved successfully.',
            'data' => CommunityPostResource::collection($posts)->resolve(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'total' => $posts->total(),
                'per_page' => $posts->perPage(),
            ],
        ]);
    }
}
