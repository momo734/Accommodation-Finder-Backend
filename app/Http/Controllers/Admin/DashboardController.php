<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccommodationResource;
use App\Http\Resources\UserResource;
use App\Models\Accommodation;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $accommodationStats = Accommodation::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected")
            ->first();

        $recentUsers = User::query()
            ->whereIn('role', ['owner', 'user'])
            ->latest()
            ->limit(5)
            ->get();

        $recentAccommodations = Accommodation::query()
            ->with(['user:id,name,email,phone,role', 'images'])
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'message' => 'Dashboard stats retrieved successfully.',
            'data' => [
                'stats' => [
                    'total_users' => User::query()->whereIn('role', ['owner', 'user'])->count(),
                    'total_owners' => User::query()->where('role', 'owner')->count(),
                    'total_accommodations' => (int) ($accommodationStats->total ?? 0),
                    'pending_approvals' => (int) ($accommodationStats->pending ?? 0),
                    'pending_accommodations' => (int) ($accommodationStats->pending ?? 0),
                    'approved_accommodations' => (int) ($accommodationStats->approved ?? 0),
                    'rejected_accommodations' => (int) ($accommodationStats->rejected ?? 0),
                    'total_reviews' => Review::query()->count(),
                    'total_reports' => Report::query()->count(),
                ],
                'recent_users' => UserResource::collection($recentUsers),
                'recent_accommodations' => AccommodationResource::collection($recentAccommodations),
            ],
        ]);
    }
}
