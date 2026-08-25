<?php

namespace App\Domains\Finance\Events;

class InvoiceReminderDue
{
    public function __construct(
        public int $invoiceId,
        public int $studentId,
        public string $studentName,
        public string $invoiceNumber,
        public string $balance,
        public string $dueDate,
    ) {}
}
