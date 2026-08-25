<?php

namespace App\Domains\Notifications\Listeners;

use App\Domains\Finance\Events\InvoiceIssued;
use App\Domains\Finance\Events\InvoiceReminderDue;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Models\UserNotification;
use App\Domains\People\Actions\ListFinanciallyResponsibleContactsAction;

class SendInvoiceGuardianNotice
{
    public function __construct(private SmsSenderInterface $sms) {}

    public function handleIssued(InvoiceIssued $event): void
    {
        $this->notify(
            $event->studentId,
            'Invoice '.$event->invoiceNumber,
            "Akuru Institute: invoice {$event->invoiceNumber} for {$event->studentName} is {$event->totalAmount} MVR, due {$event->dueDate}.",
            ['invoice_id' => $event->invoiceId, 'kind' => 'issued'],
        );
    }

    public function handleReminder(InvoiceReminderDue $event): void
    {
        $this->notify(
            $event->studentId,
            'Invoice reminder '.$event->invoiceNumber,
            "Akuru Institute: invoice {$event->invoiceNumber} for {$event->studentName} has {$event->balance} MVR unpaid (due {$event->dueDate}).",
            ['invoice_id' => $event->invoiceId, 'kind' => 'reminder'],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notify(int $studentId, string $title, string $message, array $data): void
    {
        $contacts = app(ListFinanciallyResponsibleContactsAction::class)->execute($studentId);
        foreach ($contacts as $contact) {
            if (! empty($contact['phone'])) {
                $this->sms->sendSms((string) $contact['phone'], $message, [
                    'type' => 'finance',
                    'reference' => 'invoice_'.$data['kind'].'_'.$data['invoice_id'],
                ]);
            }
            if (! empty($contact['user_id'])) {
                UserNotification::query()->create([
                    'user_id' => $contact['user_id'],
                    'type' => 'in_app',
                    'category' => 'payment',
                    'title' => $title,
                    'message' => $message,
                    'data' => $data,
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ]);
            }
        }
    }
}
