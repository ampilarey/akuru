<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'date_of_birth',
        'gender',
        'national_id',
        'photo',
        'is_active',
        'force_password_change',
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'force_password_change' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    // Relationships
    public function contacts()
    {
        return $this->hasMany(UserContact::class);
    }

    public function primaryMobile()
    {
        return $this->hasOne(UserContact::class)->where('type', 'mobile')->where('is_primary', true);
    }

    public function primaryEmail()
    {
        return $this->hasOne(UserContact::class)->where('type', 'email')->where('is_primary', true);
    }

    public function guardianStudents()
    {
        return $this->belongsToMany(RegistrationStudent::class, 'student_guardians', 'guardian_user_id', 'student_id')
            ->withPivot('relationship', 'is_primary')
            ->withTimestamps();
    }

    public function registrationStudentProfile()
    {
        return $this->hasOne(RegistrationStudent::class, 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function teacher()
    {
        return $this->hasOne(Teacher::class);
    }

    public function parentGuardian()
    {
        return $this->hasOne(ParentGuardian::class);
    }

    // Helper methods
    public function isStudent()
    {
        return $this->hasRole('student');
    }

    public function isTeacher()
    {
        return $this->hasRole('teacher');
    }

    public function isParent()
    {
        return $this->hasRole('parent');
    }

    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    public function isHeadmaster()
    {
        return $this->hasRole('headmaster');
    }

    public function isSupervisor()
    {
        return $this->hasRole('supervisor');
    }

    public function isSuperAdmin()
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Check if user is admin or higher (Super Admin or Admin)
     */
    public function isAdminLevel()
    {
        return $this->hasAnyRole(['super_admin', 'admin']);
    }

    /**
     * Check if user is management level (Super Admin, Admin, or Headmaster)
     */
    public function isManagementLevel()
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'headmaster']);
    }

    public function hasVerifiedContact(): bool
    {
        return $this->contacts()->whereNotNull('verified_at')->exists();
    }
}
