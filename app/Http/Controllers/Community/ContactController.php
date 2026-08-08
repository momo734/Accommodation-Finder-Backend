<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $item = ContactMessage::query()->create($data);

        return response()->json([
            'message' => 'Message sent successfully. We will get back to you soon.',
            'data' => [
                'id' => $item->id,
            ],
        ], 201);
    }
}
