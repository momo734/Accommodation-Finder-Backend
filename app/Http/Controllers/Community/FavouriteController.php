<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccommodationResource;
use App\Models\Accommodation;
use App\Models\Favourite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FavouriteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $favourites = Accommodation::query()
            ->where('status', 'approved')
            ->whereHas('favourites', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with(['user:id,name,email,phone,role', 'images'])
            ->withCount(['reviews' => fn ($q) => $q->where('is_hidden', false)])
            ->latest()
            ->paginate(12);

        $favourites->getCollection()->transform(function (Accommodation $item) {
            $item->setAttribute('is_favourite', true);

            return $item;
        });

        return AccommodationResource::collection($favourites)
            ->additional([
                'message' => 'Favourites retrieved successfully.',
            ]);
    }

    public function store(Request $request, Accommodation $accommodation): JsonResponse
    {
        if ($accommodation->status !== 'approved') {
            return response()->json([
                'message' => 'Accommodation not found.',
            ], 404);
        }

        Favourite::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'accommodation_id' => $accommodation->id,
        ]);

        return response()->json([
            'message' => 'Added to favourites.',
            'data' => [
                'accommodation_id' => $accommodation->id,
                'is_favourite' => true,
            ],
        ], 201);
    }

    public function destroy(Request $request, Accommodation $accommodation): JsonResponse
    {
        Favourite::query()
            ->where('user_id', $request->user()->id)
            ->where('accommodation_id', $accommodation->id)
            ->delete();

        return response()->json([
            'message' => 'Removed from favourites.',
            'data' => [
                'accommodation_id' => $accommodation->id,
                'is_favourite' => false,
            ],
        ]);
    }

    public function ids(Request $request): JsonResponse
    {
        $ids = Favourite::query()
            ->where('user_id', $request->user()->id)
            ->pluck('accommodation_id');

        return response()->json([
            'message' => 'Favourite ids retrieved successfully.',
            'data' => [
                'ids' => $ids,
            ],
        ]);
    }
}
