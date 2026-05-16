<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')
                  ->constrained('profiles')
                  ->onDelete('cascade');
            $table->unsignedBigInteger('subscribers_count')->default(0); // was followers_count
            $table->unsignedBigInteger('videos_count')->default(0); // was posts_count
            $table->unsignedBigInteger('views_count')->default(0); // total views snapshot
            $table->bigInteger('subscribers_delta')->default(0); // was followers_delta
            $table->timestampTz('fetched_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_snapshots');
    }
};