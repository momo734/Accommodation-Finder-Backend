<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            $table->string('township')->nullable()->after('city');
            $table->string('address')->nullable()->after('township');
            $table->unsignedTinyInteger('bedrooms')->nullable()->after('type');
            $table->unsignedTinyInteger('bathrooms')->nullable()->after('bedrooms');
            $table->string('furnishing')->nullable()->after('bathrooms');
        });

        // Existing rows used `location` as the township/area label.
        DB::table('accommodations')
            ->whereNull('township')
            ->update([
                'township' => DB::raw('location'),
            ]);
    }

    public function down(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            $table->dropColumn(['township', 'address', 'bedrooms', 'bathrooms', 'furnishing']);
        });
    }
};
