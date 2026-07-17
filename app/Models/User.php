<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Every attendance scan (clock-in/clock-out) this user has recorded.
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    /**
     * QR codes this user force-generated as an admin.
     * (System-rotated codes have generated_by = null, so this only
     * reflects manual refreshes, not the scheduled rotation.)
     */
    public function generatedQrCodes(): HasMany
    {
        return $this->hasMany(AttendanceQrCode::class, 'generated_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
