<?php

namespace App\Mail;

use App\Models\Esim;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 9E: "Your eSIM is ready". Contains NO activation data (no activation
 * code, SM-DP+ address, LPA string or QR image): only links to the customer's
 * eSIM pages, which require signing in.
 */
class EsimReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public const SUBJECT = 'Your eSIM is ready – Eazy Communication';

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::SUBJECT);
    }

    public function content(): Content
    {
        $items = $this->order->items()->get()->keyBy('id');
        $esims = Esim::query()->whereIn('order_item_id', $items->keys())->orderBy('id')->get();

        return new Content(markdown: 'mail.esim-ready', with: [
            'name' => $this->order->user?->name ?: $this->order->billing_name,
            'orderNumber' => $this->order->order_number,
            'esims' => $esims->map(fn (Esim $e) => [
                'bundle' => $items[$e->order_item_id]?->plan_name ?? 'eSIM',
                'url' => route('my-esims.show', $e),
            ])->all(),
            'esimsUrl' => route('my-esims'),
            'orderUrl' => route('orders.show', $this->order),
            'supportUrl' => route('esim-support'),
        ]);
    }
}
