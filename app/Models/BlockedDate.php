<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $blocked_date
 * @property string $reason
 * @property string|null $attendant_name
 * @property int|null $attendant_user_id
 * @property-read string $attendant_display_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BlockedDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'blocked_date',
        'reason',
        'attendant_name',
        'attendant_user_id',
    ];

    protected function casts(): array
    {
        return [
            'blocked_date' => 'date',
        ];
    }

    public function attendantUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendant_user_id');
    }

    public function getAttendantDisplayNameAttribute(): string
    {
        $relationName = trim((string) ($this->attendantUser?->name ?? ''));

        return $relationName !== '' ? $relationName : (string) ($this->attendant_name ?? '');
    }
}
