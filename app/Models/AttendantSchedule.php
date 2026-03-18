<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
