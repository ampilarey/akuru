<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A customer's address book (BOOKSHOP_PLAN §4). The customer's own: they
 * may delete one at any time; orders keep their own snapshot (audit
 * finding 13).
 */
class CustomerAddress extends Model
{
    protected $fillable = ['user_id', 'label', 'recipient_name', 'phone', 'atoll', 'island', 'street', 'notes', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /**
     * @return array{recipient_name: string, phone: string, atoll: string, island: string, street: string, notes: ?string}
     */
    public function snapshot(): array
    {
        return [
            'recipient_name' => (string) $this->recipient_name,
            'phone' => (string) $this->phone,
            'atoll' => (string) $this->atoll,
            'island' => (string) $this->island,
            'street' => (string) $this->street,
            'notes' => $this->notes,
        ];
    }
}
