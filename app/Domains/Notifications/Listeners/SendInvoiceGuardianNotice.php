<?php

namespace App\Domains\Notifications\Listeners;

use App\Domains\Finance\Events\InvoiceIssued;
use App\Domains\Finance\Events\InvoiceReminderDue;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Models\UserNotification;
use App\Domains\People\Actions\ListFinanciallyResponsibleContactsAction;
use App\Support\Said;

class SendInvoiceGuardianNotice
{
    public function __construct(private SmsSenderInterface $sms) {}

    public function handleIssued(InvoiceIssued $event): void
    {
        $this->notify(
            $event->studentId,
            Said::of('notifications.invoice.issued_title', ['number' => $event->invoiceNumber]),
            Said::of('notifications.invoice.issued_body', ['number' => $event->invoiceNumber, 'name' => $event->studentName, 'amount' => $event->totalAmount, 'due' => $event->dueDate]),
            ['invoice_id' => $event->invoiceId, 'kind' => 'issued'],
        );
    }

    public function handleReminder(InvoiceReminderDue $event): void
    {
        $this->notify(
            $event->studentId,
            Said::of('notifications.invoice.reminder_title', ['number' => $event->invoiceNumber]),
            Said::of('notifications.invoice.reminder_body', ['number' => $event->invoiceNumber, 'name' => $event->studentName, 'amount' => $event->balance, 'due' => $event->dueDate]),
            ['invoice_id' => $event->invoiceId, 'kind' => 'reminder'],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notify(int $studentId, Said $title, Said $message, array $data): void
    {
        // The in-app row keeps the phrase for the reader's language (ADR-044);
        // the SMS leaves now, in the sender's.
        $data['said'] = ['title' => $title->toArray(), 'message' => $message->toArray()];
        $contacts = app(ListFinanciallyResponsibleContactsAction::class)->execute($studentId);
        foreach ($contacts as $contact) {
            if (! empty($contact['phone'])) {
                // LA1: in the guardian's own language when their account keeps one.
                $locale = empty($contact['user_id']) ? null : config('auth.providers.users.model')::query()->whereKey($contact['user_id'])->value('locale');
                $this->sms->sendSms((string) $contact['phone'], $message->in($locale), [
                    'type' => 'finance',
                    'reference' => 'invoice_'.$data['kind'].'_'.$data['invoice_id'],
                ]);
            }
            if (! empty($contact['user_id'])) {
                UserNotification::query()->create([
                    'user_id' => $contact['user_id'],
                    'type' => 'in_app',
                    'category' => 'payment',
                    'title' => (string) $title,
                    'message' => (string) $message,
                    'data' => $data,
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ]);
            }
        }
    }
}
