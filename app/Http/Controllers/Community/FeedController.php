<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccommodationResource;
use App\Http\Resources\CommunityPostResource;
use App\Models\Accommodation;
use App\Models\CommunityPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Combined public feed — one request instead of two (faster Home / Hostels).
 */
class FeedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'city' => ['nullable', Rule::in(['Yangon', 'Mandalay'])],
            'township' => ['nullable', 'string', 'max:255'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'type' => ['nullable', 'string', 'max:100'],
            'listings_limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'posts_limit' => ['nullable', 'integer', 'min:0', 'max:50'],
            // Axios sends "true"/"false" strings — accept loosely.
            'include_totals' => ['nullable'],
        ]);

        $listingsLimit = (int) ($filters['listings_limit'] ?? 12);
        $postsLimit = array_key_exists('posts_limit', $filters)
            ? (int) $filters['posts_limit']
            : 12;
        $includeTotals = filter_var($request->input('include_totals', false), FILTER_VALIDATE_BOOLEAN);
        $hasRoomType = filled($filters['type'] ?? null);

        $listings = Accommodation::query()
            ->select([
                'id', 'user_id', 'title', 'description', 'city', 'township', 'address',
                'location', 'price', 'type', 'bedrooms', 'bathrooms', 'furnishing', 'status', 'created_at',
            ])
            ->with([
                'user:id,name,role',
                'images:id,accommodation_id,image_path,is_primary,sort_order',
            ])
            ->where('status', 'approved')
            ->when($filters['city'] ?? null, fn ($q, $city) => $q->where('city', $city))
            ->when($filters['township'] ?? null, function ($q, string $township) {
                $q->where(function ($inner) use ($township) {
                    $inner->where('township', $township)->orWhere('location', $township);
                });
            })
            ->when($filters['min_price'] ?? null, fn ($q, $min) => $q->where('price', '>=', $min))
            ->when($filters['max_price'] ?? null, fn ($q, $max) => $q->where('price', '<=', $max))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->latest('id')
            ->limit($listingsLimit)
            ->get();

        $posts = collect();
        if (! $hasRoomType && $postsLimit > 0) {
            $posts = CommunityPost::query()
                ->select([
                    'id', 'user_id', 'title', 'description', 'post_type', 'city', 'township',
                    'budget', 'contact_phone', 'image_path', 'status', 'created_at',
                ])
                ->with([
                    'user:id,name,role',
                    'images:id,community_post_id,image_path,is_primary,sort_order',
                ])
                ->where('status', 'approved')
                ->where('post_type', 'accommodation_available')
                ->when($filters['city'] ?? null, fn ($q, $city) => $q->where('city', $city))
                ->when($filters['township'] ?? null, fn ($q, $township) => $q->where('township', $township))
                ->when($filters['min_price'] ?? null, fn ($q, $min) => $q->where('budget', '>=', $min))
                ->when($filters['max_price'] ?? null, fn ($q, $max) => $q->where('budget', '<=', $max))
                ->latest('id')
                ->limit($postsLimit)
                ->get();
        }

        $payload = [
            'listings' => AccommodationResource::collection($listings)->resolve(),
            'posts' => CommunityPostResource::collection($posts)->resolve(),
            'listings_total' => $listings->count(),
            'posts_total' => $posts->count(),
        ];

        // Optional exact totals (slower) — only when requested.
        if ($includeTotals) {
            $payload['listings_total'] = Accommodation::query()
                ->where('status', 'approved')
                ->when($filters['city'] ?? null, fn ($q, $city) => $q->where('city', $city))
                ->when($filters['township'] ?? null, function ($q, string $township) {
                    $q->where(function ($inner) use ($township) {
                        $inner->where('township', $township)->orWhere('location', $township);
                    });
                })
                ->when($filters['min_price'] ?? null, fn ($q, $min) => $q->where('price', '>=', $min))
                ->when($filters['max_price'] ?? null, fn ($q, $max) => $q->where('price', '<=', $max))
                ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
                ->count();

            $payload['posts_total'] = $hasRoomType
                ? 0
                : CommunityPost::query()
                    ->where('status', 'approved')
                    ->where('post_type', 'accommodation_available')
                    ->when($filters['city'] ?? null, fn ($q, $city) => $q->where('city', $city))
                    ->when($filters['township'] ?? null, fn ($q, $township) => $q->where('township', $township))
                    ->when($filters['min_price'] ?? null, fn ($q, $min) => $q->where('budget', '>=', $min))
                    ->when($filters['max_price'] ?? null, fn ($q, $max) => $q->where('budget', '<=', $max))
                    ->count();
        }

        return response()->json([
            'message' => 'Feed retrieved successfully.',
            'data' => $payload,
        ]);
    }
}
