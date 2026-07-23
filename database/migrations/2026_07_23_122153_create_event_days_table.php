<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('event_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('day_number'); // 1, 2, 3...
            $table->timestamps();

            $table->unique(['event_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_days');
    }
};
