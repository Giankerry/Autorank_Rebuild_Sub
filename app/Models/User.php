<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Panel;
use Google\Service\Drive;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'faculty_rank_id',
        'rank_assigned_by',
        'rank_assigned_at',
        'google_id',
        'google_token',
        'google_refresh_token',
        'avatar_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rank_assigned_at' => 'datetime',
            'google_token' => 'array',
        ];
    }

    /**
     * Defines the relationship to the FacultyRank model.
     */
    public function facultyRank(): BelongsTo
    {
        return $this->belongsTo(FacultyRank::class);
    }

    /**
     * Get all applications for the user
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'user_id');
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url;
    }

    /**
     * Required by FilamentUser interface
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Check if the user's Google token has the required Drive scope.
     */
    public function hasDriveScope(): bool
    {
        $tokenData = $this->google_token;

        if (empty($tokenData) || !is_array($tokenData) || empty($tokenData['scope'])) {
            return false;
        }
        $scopes = explode(' ', $tokenData['scope']);

        return in_array(Drive::DRIVE_FILE, $scopes);
    }
}
