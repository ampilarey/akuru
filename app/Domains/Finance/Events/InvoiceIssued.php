<?php

namespace App\Domains\Finance\Events;

class InvoiceIssued
{
    public function __construct(
        public int $invoiceId,
        public int $studentId,
        public string $studentName,
        public string $invoiceNumber,
        public string $totalAmount,
        public string $dueDate,
    ) {}
}
