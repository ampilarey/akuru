<?php

namespace App\Domains\People\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ParentGuardian extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'first_name_arabic',
        'first_name_dhivehi',
        'last_name',
        'last_name_arabic',
        'last_name_dhivehi',
        'phone',
        'email',
        'address',
        'occupation',
        'occupation_arabic',
        'occupation_dhivehi',
        'national_id',
        'relationship',
        'photo',
        'is_emergency_contact',
    ];

    protected $casts = [
        'is_emergency_contact' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The guardian's children, through `guardian_student` — the pivot the rest
     * of the product actually writes.
     *
     * Prefer this to `students()`. There are two guardian↔student pivots in
     * this schema and only this one is live: every notification listener
     * (absence SMS, behaviour SMS, exam results, report cards) and every People
     * action (collection policy, financial responsibility, guardian access)
     * reads `guardian_student`. `student_parent` is written by nothing in the
     * application — `HifzDemoSeeder` was its only writer, which is why the Hifz
     * parent dashboard worked for the demo parent and 403'd for every real one.
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student', 'guardian_id', 'student_id')
            ->withPivot('relationship', 'is_primary', 'can_pickup', 'financial_responsible')
            ->withTimestamps();
    }

    /**
     * @deprecated Reads the legacy `student_parent` pivot, which nothing writes.
     *             Use `children()`. Kept because the table is populated and
     *             rule 9 does not drop populated tables in the deploy that
     *             stops using them.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_parent')
            ->withPivot('relationship', 'is_primary_contact')
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** Deploy 2 read compatibility: enrollment views used User.name on RS guardians. */
    public function getNameAttribute(): string
    {
        return $this->full_name;
    }
}
