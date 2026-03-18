<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $student_name
 * @property string $student_registration
 * @property string $attendant_name
 * @property int|null $attendant_user_id
 * @property string $subject
 * @property Carbon $scheduled_at
 * @property string $status
 * @property string|null $cancellation_reason
 * @property-read string $attendant_display_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_name',
        'student_registration',
        'attendant_name',
        'attendant_user_id',
        'subject',
        'scheduled_at',
        'status',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function attendantUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendant_user_id');
    }

    public function getAttendantDisplayNameAttribute(): string
    {
        $relationName = trim((string) ($this->attendantUser?->name ?? ''));

        return $relationName !== '' ? $relationName : (string) $this->attendant_name;
    }
}
