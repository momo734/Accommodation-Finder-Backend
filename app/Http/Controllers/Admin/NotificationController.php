<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\CommunityPost;
use App\Models\ContactMessage;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Pending accommodations + community posts + unread contact messages.
     */
    public function index(): JsonResponse
    {
        $listings = Accommodation::query()
            ->with(['user:id,name,role', 'images'])
            ->where('status', 'pending')
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (Accommodation $item) {
                return [
                    'id' => 'listing-'.$item->id,
                    'type' => 'listing',
                    'target_id' => $item->id,
                    'title' => $item->title,
                    'posted_by' => $item->user?->name ?? 'Unknown',
                    'poster_role' => $item->user?->role ?? 'user',
                    'image' => MediaUrl::public($item->images->first()?->image_path),
                    'city' => $item->city,
                    'created_at' => $item->created_at,
                ];
            });

        $posts = CommunityPost::query()
            ->with(['user:id,name,role', 'images'])
            ->where('status', 'pending')
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (CommunityPost $item) {
                $image = $item->images->first()?->image_path ?: $item->image_path;

                return [
                    'id' => 'post-'.$item->id,
                    'type' => 'community_post',
                    'target_id' => $item->id,
                    'title' => $item->title,
                    'posted_by' => $item->user?->name ?? 'Unknown',
                    'poster_role' => $item->user?->role ?? 'user',
                    'image' => MediaUrl::public($image),
                    'city' => $item->city,
                    'created_at' => $item->created_at,
                ];
            });

        $contacts = ContactMessage::query()
            ->where('is_read', false)
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (ContactMessage $item) {
                return [
                    'id' => 'contact-'.$item->id,
                    'type' => 'contact_message',
                    'target_id' => $item->id,
                    'title' => $item->subject,
                    'posted_by' => $item->name,
                    'poster_role' => 'contact',
                    'email' => $item->email,
                    'preview' => mb_strimwidth($item->message, 0, 120, '…'),
                    'image' => null,
                    'city' => null,
                    'created_at' => $item->created_at,
                ];
            });

        $items = $listings->concat($posts)->concat($contacts)
            ->sortByDesc(fn ($row) => strtotime((string) $row['created_at']))
            ->values();

        $pendingListings = Accommodation::query()->where('status', 'pending')->count();
        $pendingPosts = CommunityPost::query()->where('status', 'pending')->count();
        $unreadContacts = ContactMessage::query()->where('is_read', false)->count();

        return response()->json([
            'message' => 'Notifications retrieved successfully.',
            'data' => [
                'items' => $items,
                'count' => $pendingListings + $pendingPosts + $unreadContacts,
                'pending_listings' => $pendingListings,
                'pending_posts' => $pendingPosts,
                'unread_contacts' => $unreadContacts,
            ],
        ]);
    }
}
