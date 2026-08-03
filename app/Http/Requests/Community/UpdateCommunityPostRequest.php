<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunityPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof \App\Models\CommunityPost
            && $this->user()?->id === $post->user_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isOwner = $this->user()?->role === 'owner';
        $allowedType = $isOwner
            ? 'accommodation_available'
            : 'looking_for_accommodation';

        $post = $this->route('post');
        $hasExistingImage = $post instanceof \App\Models\CommunityPost
            && (
                filled($post->image_path)
                || $post->images()->exists()
            );

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'post_type' => ['required', Rule::in([$allowedType])],
            'city' => ['required', Rule::in(['Yangon', 'Mandalay'])],
            'township' => ['required', 'string', 'max:255'],
            'budget' => ['required', 'numeric', 'min:0'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'image' => ['nullable', 'image', 'max:4096'],
            'images' => [
                $isOwner && ! $hasExistingImage ? 'required' : 'nullable',
                'array',
                'max:6',
            ],
            'images.*' => ['image', 'max:4096'],
        ];
    }
}
