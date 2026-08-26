<?php

namespace App\Domains\Courses\Models;

use App\Domains\Courses\Enums\CertificateKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'name_dv',
        'name_ar',
        'kind',
        'course_id',
        'rules',
        'body_html',
        'active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CertificateKind::class,
            'rules' => 'array',
            'active' => 'boolean',
        ];
    }
}
