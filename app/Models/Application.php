<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Application extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'applicant_current_rank',
        'status',
        'evaluation_cycle',
        'kra1_score',
        'kra2_score',
        'kra3_score',
        'kra4_score',
        'final_score',
        'highest_attainable_rank',
        'remarks',
    ];

    /**
     * Get the user that owns the application.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all of the submissions for the application.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    protected function applicantCurrentRank(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($this->status === 'draft') {
                    return $this->user->facultyRank?->name ?? 'Unset';
                }

                return $value;
            }
        );
    }
}
