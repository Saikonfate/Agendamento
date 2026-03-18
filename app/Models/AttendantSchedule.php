<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $attendant_name
 * @property int|null $attendant_user_id
 * @property array<int, string>|null $working_days
 * @property array<string, mixed>|null $day_settings
 * @property string $start_time
 * @property string $end_time
 * @property string|null $break_start
 * @property string|null $break_end
 * @property int $slot_duration_minutes
 */
class AttendantSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendant_name',
        'attendant_user_id',
        'working_days',
        'day_settings',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'slot_duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'working_days' => 'array',
            'day_settings' => 'array',
        ];
    }

    public function attendantUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendant_user_id');
    }
}
