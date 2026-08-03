<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->after('image_path');
            $table->index(['status', 'created_at']);
            $table->index(['status', 'city']);
        });

        // Keep existing demo posts visible publicly.
        DB::table('community_posts')->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['status', 'city']);
            $table->dropColumn('status');
        });
    }
};
