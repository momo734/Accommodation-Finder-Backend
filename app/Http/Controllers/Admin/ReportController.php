<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportIndexRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(ReportIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $reports = Report::query()
            ->with([
                'user:id,name,email,role',
                'accommodation:id,title,city,location,status',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reason', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('accommodation', function ($accommodationQuery) use ($search) {
                            $accommodationQuery->where('title', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        return ReportResource::collection($reports)
            ->additional([
                'message' => 'Reports retrieved successfully.',
            ]);
    }

    public function show(Report $report): JsonResponse
    {
        $report->load([
            'user:id,name,email,role',
            'accommodation:id,title,city,location,status',
        ]);

        return response()->json([
            'message' => 'Report retrieved successfully.',
            'data' => new ReportResource($report),
        ]);
    }

    public function resolve(Report $report): JsonResponse
    {
        if ($report->status === 'resolved') {
            return response()->json([
                'message' => 'Report is already resolved.',
            ], 422);
        }

        $report->update(['status' => 'resolved']);
        $report->load([
            'user:id,name,email,role',
            'accommodation:id,title,city,location,status',
        ]);

        return response()->json([
            'message' => 'Report resolved successfully.',
            'data' => new ReportResource($report),
        ]);
    }

    public function removeListing(Report $report): JsonResponse
    {
        DB::transaction(function () use ($report) {
            $accommodation = $report->accommodation;

            $report->update(['status' => 'resolved']);

            if ($accommodation) {
                $accommodation->delete();
            }
        });

        return response()->json([
            'message' => 'Listing removed and report resolved.',
        ]);
    }

    public function destroy(Report $report): JsonResponse
    {
        $report->delete();

        return response()->json([
            'message' => 'Report deleted successfully.',
        ]);
    }
}
