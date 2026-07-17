<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['clock_in', 'clock_out']);
            $table->timestamp('scanned_at');
            $table->foreignId('qr_code_id')->constrained('attendance_qr_codes');
            $table->string('ip_address', 45)->nullable();
            $table->string('device_type')->nullable(); // mobile | tablet | desktop

            // Reserved for Phase 2 geofencing — nullable so Phase 1 scans
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
