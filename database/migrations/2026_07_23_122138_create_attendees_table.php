<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('organisation')->nullable();
            $table->string('email')->nullable();
            $table->string('role_title')->nullable(); // e.g. Delegate, Speaker, VIP — free text for Phase 1
            $table->timestamps();

            $table->index(['name', 'organisation']);
        });
       
    }

    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};
