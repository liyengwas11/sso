<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('venue')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_recurring')->default(false);
            $table->foreignId('parent_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        // Cover image lives in Spatie Media Library's own media table.
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
