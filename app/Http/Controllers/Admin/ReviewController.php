<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewIndexRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    public function index(ReviewIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $reviews = Review::query()
            ->with([
                'user:id,name,email,role',
                'accommodation:id,title,city,location,status',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('comment', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('accommodation', function ($accommodationQuery) use ($search) {
                            $accommodationQuery->where('title', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['rating'] ?? null, fn ($query, int $rating) => $query->where('rating', $rating))
            ->when(($filters['status'] ?? null) === 'visible', fn ($query) => $query->where('is_hidden', false))
            ->when(($filters['status'] ?? null) === 'hidden', fn ($query) => $query->where('is_hidden', true))
            ->when(
                $filters['accommodation_id'] ?? null,
                fn ($query, int $accommodationId) => $query->where('accommodation_id', $accommodationId)
            )
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        return ReviewResource::collection($reviews)
            ->additional([
                'message' => 'Reviews retrieved successfully.',
            ]);
    }

    public function hide(Review $review): JsonResponse
    {
        if ($review->is_hidden) {
            return response()->json([
                'message' => 'Review is already hidden.',
            ], 422);
        }

        $review->update(['is_hidden' => true]);
        $review->load([
            'user:id,name,email,role',
            'accommodation:id,title,city,location,status',
        ]);

        return response()->json([
            'message' => 'Review hidden successfully.',
            'data' => new ReviewResource($review),
        ]);
    }

    public function destroy(Review $review): JsonResponse
    {
        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully.',
        ]);
    }
}
