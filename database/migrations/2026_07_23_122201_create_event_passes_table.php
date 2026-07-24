<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->unique(['event_id', 'attendee_id']);
        });

        
        Schema::create('event_pass_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_pass_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_day_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_pass_id', 'event_day_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_pass_days');
        Schema::dropIfExists('event_passes');
    }
};
