<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stripe\Checkout\Session;

class StripeEventsModel extends Model
{
    protected $table = 'stripe_events';

    public $timestamps = false;

    protected $fillable = [
        'stripe_event_id',
        'type',
        'api_version',
        'livemode',
        'object_id',
        'checkout_session_id',
        'payment_intent_id',
        'customer_email',
        'amount_total',
        'currency',
        'payment_status',
        'payload',
        'stripe_created_at',
        'received_at',
        'processing_status',
        'processed_at',
        'error_message',
        'attempts',
    ];

    protected $casts = [
        'livemode' => 'boolean',
        'payload' => 'array',
        'stripe_created_at' => 'datetime',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public static function logCheckoutSession(Session $session): self
    {
        $sessionId = $session->id;
        $paymentIntent = $session->payment_intent;
        $customerDetails = $session->customer_details;

        return self::updateOrCreate(
            ['stripe_event_id' => 'checkout_session_' . $sessionId],
            [
                'type' => 'checkout.session.created',
                'livemode' => (bool) $session->livemode,
                'object_id' => $sessionId,
                'checkout_session_id' => $sessionId,
                'payment_intent_id' => is_string($paymentIntent)
                    ? $paymentIntent
                    : ($paymentIntent->id ?? null),
                'customer_email' => $customerDetails->email
                    ?? $session->customer_email
                    ?? null,
                'amount_total' => $session->amount_total,
                'currency' => $session->currency,
                'payment_status' => $session->payment_status,
                'payload' => $session->toArray(),
                'stripe_created_at' => gmdate('Y-m-d H:i:s', (int) $session->created),
                'processing_status' => 'processed',
                'processed_at' => gmdate('Y-m-d H:i:s'),
                'attempts' => 1,
            ]
        );
    }
}
