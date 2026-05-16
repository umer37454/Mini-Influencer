<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('username'); // youtube handle e.g. mkbhd
            $table->string('channel_id')->nullable(); // youtube channel ID e.g. UCBcRF18a7Qf58cCRy5xuWwQ
            $table->string('platform')->default('youtube');
            $table->string('profile_url')->nullable(); // https://youtube.com/@mkbhd
            $table->string('full_name')->nullable(); // "Marques Brownlee"
            $table->text('bio')->nullable(); // channel description
            $table->string('profile_picture_url')->nullable(); // channel thumbnail
            $table->unsignedBigInteger('subscribers_count')->default(0); // was followers_count
            $table->unsignedBigInteger('videos_count')->default(0); // was posts_count
            $table->unsignedBigInteger('views_count')->default(0); // total channel views - youtube specific
            $table->enum('status', ['pending', 'fetching', 'fetched', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestampTz('last_refreshed_at')->nullable();
            $table->timestampsTz();
        });

        // unique lowercase username
        DB::statement('
            CREATE UNIQUE INDEX profiles_username_platform_unique
            ON profiles (LOWER(username), platform)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};