<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AttendanceLog Model
 *
 * Records each RFID scan at the school gate.
 * Tracks direction (in/out) and the exact scan timestamp.
 */
class AttendanceLog extends Model
{
    protected $fillable = [
        'student_id',
        'school_id',
        'rfid_code',
        'scanned_at',
        'direction',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * The student who was scanned.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * The school where the scan occurred.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
