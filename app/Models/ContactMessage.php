<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'message',
        'ip',
        'user_agent',
        'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    public function scopeUnhandled($query)
    {
        return $query->whereNull('handled_at');
    }

    public function scopeHandled($query)
    {
        return $query->whereNotNull('handled_at');
    }

    public function markAsHandled()
    {
        $this->update(['handled_at' => now()]);
    }
}
