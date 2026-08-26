<?php

namespace App\Domains\Courses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuedCertificate extends Model
{
    protected $fillable = [
        'certificate_template_id',
        'student_id',
        'course_id',
        'course_offering_id',
        'enrollment_id',
        'assessment_id',
        'academic_year_id',
        'term_id',
        'public_id',
        'certificate_number',
        'completion_date',
        'grade',
        'attendance_percent',
        'document_id',
        'issued_by',
        'issued_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'completion_date' => 'date',
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
            'attendance_percent' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }
}
