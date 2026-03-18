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
}
