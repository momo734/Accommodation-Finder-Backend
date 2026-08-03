<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Accommodation;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    public function index(Accommodation $accommodation): AnonymousResourceCollection
    {
        if ($accommodation->status !== 'approved') {
            abort(404, 'Hostel not found.');
        }

        $reviews = Review::query()
            ->where('accommodation_id', $accommodation->id)
            ->where('is_hidden', false)
            ->with('user:id,name,email,role')
            ->latest()
            ->paginate(20);

        return ReviewResource::collection($reviews)
            ->additional([
                'message' => 'Reviews retrieved successfully.',
            ]);
    }

    public function store(Request $request, Accommodation $accommodation): JsonResponse
    {
        if ($accommodation->status !== 'approved') {
            return response()->json([
                'message' => 'Hostel not found.',
            ], 404);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = Review::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'accommodation_id' => $accommodation->id,
            ],
            [
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'is_hidden' => false,
            ]
        );

        $review->load('user:id,name,email,role');

        return response()->json([
            'message' => 'Review saved successfully.',
            'data' => new ReviewResource($review),
        ], 201);
    }
}
