<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityPost extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'post_type',
        'city',
        'township',
        'budget',
        'contact_phone',
        'image_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(CommunityPostImage::class)->orderBy('sort_order');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommunityPostLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CommunityPostComment::class);
    }

    public function favourites(): HasMany
    {
        return $this->hasMany(CommunityPostFavourite::class);
    }
}
