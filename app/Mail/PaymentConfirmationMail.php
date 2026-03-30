<?php

namespace App\Mail;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Payment $payment
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmation de votre commande DOCSFLOW'
        );
    }

    public function content(): Content
    {
        $metadata = (array) ($this->payment->metadata ?? []);
        $trialEnd = $this->trialEnd($metadata['subscription_trial_end'] ?? null);

        return new Content(
            view: 'emails.payment-confirmation',
            with: [
                'customerName' => $this->customerName(),
                'amount' => $this->formatAmount((float) ($this->payment->amount ?? 0)),
                'currency' => strtoupper((string) ($this->payment->currency ?: 'EUR')),
                'identifier' => $this->payment->siret_or_siren ?: 'Non renseigné',
                'companyName' => $this->payment->company_name ?: null,
                'trialHours' => (int) config('stripe.trial_hours', 72),
                'trialEnd' => $trialEnd,
                'supportEmail' => (string) config('mail.from.address', 'Contact@docsflow.fr'),
                'monthlyAmount' => $this->formatAmount((float) config('stripe.recurring_amount', 49.99)),
                'orderReference' => $this->payment->stripe_intent_id ?: 'Commande #'.$this->payment->id,
            ]
        );
    }

    private function customerName(): string
    {
        $parts = array_filter([
            $this->payment->first_name,
            $this->payment->last_name,
        ]);

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        if (!empty($this->payment->holder_name)) {
            return $this->payment->holder_name;
        }

        return 'Bonjour';
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, ',', ' ');
    }

    private function trialEnd(mixed $timestamp): ?string
    {
        if (empty($timestamp)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp)
            ->timezone(config('app.timezone', 'UTC'))
            ->locale('fr')
            ->translatedFormat('d/m/Y à H:i');
    }
}
