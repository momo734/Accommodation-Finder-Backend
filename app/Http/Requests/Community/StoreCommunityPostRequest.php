<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCommunityPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'post_type' => ['required', Rule::in([$allowedType])],
            'city' => ['required', Rule::in(['Yangon', 'Mandalay'])],
            'township' => ['required', 'string', 'max:255'],
            'budget' => ['required', 'numeric', 'min:0'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'image' => ['nullable', 'image', 'max:4096'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['image', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->user()?->role !== 'owner') {
                return;
            }

            $hasImages = $this->hasFile('images') || $this->hasFile('image');
            if (! $hasImages) {
                $validator->errors()->add('images', 'Please upload at least one hostel photo.');
            }
        });
    }
}
