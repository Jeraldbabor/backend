<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Model
 *
 * Includes HasApiTokens trait from Sanctum for token-based API authentication.
 * The 'role' column determines user type: admin, parent, or student.
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // Added: user role (superadmin, admin, parent, student)
        'school_id',
        'profile_image',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profile_image_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // Automatically hashes password on set
        ];
    }

    /**
     * Check if the user has the 'admin' role.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if the user has the 'superadmin' role.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    /**
     * Check if the user has the 'teacher' role.
     */
    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    /**
     * Check if the user has the 'principal' role.
     */
    public function isPrincipal(): bool
    {
        return $this->role === 'principal';
    }

    /**
     * Check if the user has the 'parent' role.
     */
    public function isParent(): bool
    {
        return $this->role === 'parent';
    }

    /**
     * Get the full URL to the user's profile image.
     */
    public function getProfileImageUrlAttribute(): ?string
    {
        if ($this->profile_image) {
            return url(\Illuminate\Support\Facades\Storage::url($this->profile_image));
        }
        return null;
    }

    /**
     * Get the user's school.
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get students linked to this parent user.
     */
    public function students()
    {
        return $this->hasMany(Student::class, 'parent_id');
    }

    /**
     * Get teacher assignments for this user.
     */
    public function teacherAssignments()
    {
        return $this->hasMany(Teacher::class);
    }

    /**
     * Get notifications for this user.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}
