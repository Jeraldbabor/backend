<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Student Model
 *
 * Represents an enrolled student with an optional RFID code for gate scanning.
 * Linked to a school and optionally to a parent user account.
 */
class Student extends Model
{
    protected $fillable = [
        'school_id',
        'parent_id',
        'first_name',
        'last_name',
        'grade',
        'section',
        'rfid_code',
        'student_id_number',
    ];

    /**
     * Get the student's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * The school this student belongs to.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * The parent user associated with this student.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /**
     * All attendance logs for this student.
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
