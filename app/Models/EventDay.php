<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EventDay extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'date', 'day_number'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function passes(): BelongsToMany
    {
        return $this->belongsToMany(EventPass::class, 'event_pass_days');
    }
}
