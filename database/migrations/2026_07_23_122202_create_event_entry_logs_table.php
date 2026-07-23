<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_entry_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_pass_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_day_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scanned_by')->constrained('users');
            $table->timestamp('scanned_at');
            $table->enum('result', ['granted', 'denied', 'override_granted']);
            $table->string('reason')->nullable(); // already_checked_in, revoked, wrong_event, not_accredited_today, staff_override
            $table->string('device_type')->nullable();
            $table->boolean('flagged')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['event_pass_id', 'scanned_at']);
            $table->index('flagged');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_entry_logs');
    }
};
