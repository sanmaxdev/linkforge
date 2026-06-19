<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Where the ad shows: 'interstitial' (link redirect page) or 'dashboard'
            // (a banner in the free user's dashboard).
            $table->string('placement')->default('interstitial')->index();
            $table->text('code')->nullable();        // ad-network HTML/JS snippet (e.g. AdSense)
            $table->string('image_path')->nullable(); // OR an uploaded banner image
            $table->string('target_url')->nullable(); // click destination for an image banner
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
