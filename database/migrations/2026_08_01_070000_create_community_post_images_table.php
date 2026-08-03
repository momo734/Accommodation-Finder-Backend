<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_post_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['community_post_id', 'sort_order']);
        });

        // Move existing single images into the gallery table.
        $posts = DB::table('community_posts')
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->get(['id', 'image_path', 'created_at', 'updated_at']);

        foreach ($posts as $post) {
            DB::table('community_post_images')->insert([
                'community_post_id' => $post->id,
                'image_path' => $post->image_path,
                'is_primary' => true,
                'sort_order' => 0,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_post_images');
    }
};
