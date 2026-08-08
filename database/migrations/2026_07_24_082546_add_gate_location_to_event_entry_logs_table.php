<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_entry_logs', function (Blueprint $table) {
            $table->enum('type', ['check-in', 'check-out'])
                ->after('event_day_id')
                ->default('check-in'); // Default for existing records

            // Optional gate/location field
            $table->string('gate_location')
                ->nullable()
                ->after('device_type');

            // For tracking original check-in on duplicate attempts
            $table->unsignedBigInteger('original_staff_id')
                ->nullable()
                ->after('reviewed_by');

            // Foreign key for original staff
            $table->foreign('original_staff_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_entry_logs', function (Blueprint $table) {
            $table->dropForeign(['original_staff_id']);
            $table->dropColumn(['type', 'gate_location', 'original_staff_id']);
        });
    }
};
