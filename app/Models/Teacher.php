<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teacher Model
 *
 * Represents a teacher's assignment to a specific grade and section.
 * A single user (role=teacher) can have multiple Teacher records
 * if they handle multiple sections.
 */
class Teacher extends Model
{
    protected $fillable = [
        'user_id',
        'school_id',
        'grade',
        'section',
    ];

    /**
     * The user account for this teacher.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The school this teacher assignment belongs to.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
