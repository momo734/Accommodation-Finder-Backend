<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'unread' => ['nullable'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $unreadOnly = filter_var($request->input('unread', false), FILTER_VALIDATE_BOOLEAN);

        $messages = ContactMessage::query()
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->when($unreadOnly, fn ($q) => $q->where('is_read', false))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20);

        return response()->json([
            'message' => 'Contact messages retrieved successfully.',
            'data' => $messages,
            'unread_count' => ContactMessage::query()->where('is_read', false)->count(),
        ]);
    }

    public function show(ContactMessage $contactMessage): JsonResponse
    {
        if (! $contactMessage->is_read) {
            $contactMessage->update(['is_read' => true]);
        }

        return response()->json([
            'message' => 'Contact message retrieved successfully.',
            'data' => $contactMessage->fresh(),
        ]);
    }

    public function markRead(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->update(['is_read' => true]);

        return response()->json([
            'message' => 'Message marked as read.',
            'data' => $contactMessage->fresh(),
        ]);
    }

    public function destroy(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->delete();

        return response()->json([
            'message' => 'Message deleted successfully.',
        ]);
    }
}
