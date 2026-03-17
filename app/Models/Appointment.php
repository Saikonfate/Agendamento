<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $student_name
 * @property string $student_registration
 * @property string $attendant_name
 * @property string $subject
 * @property Carbon $scheduled_at
 * @property string $status
 * @property string|null $cancellation_reason
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
}
