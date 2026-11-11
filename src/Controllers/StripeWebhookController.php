<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\OrdersModel;
use App\Models\StripeEventsModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

class StripeWebhookController
{
    private const HANDLED_EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
    ];

    public function receive(Request $request, Response $response): Response
    {
        $secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
        if ($secret === '') {
            error_log('Stripe webhook configuration error: STRIPE_WEBHOOK_SECRET is not set.');
            return $this->jsonResponse($response, ['error' => 'Webhook is not configured.'], 500);
        }

        $payload = (string) $request->getBody();
        $signature = $request->getHeaderLine('Stripe-Signature');
        if ($payload === '' || $signature === '') {
            return $this->jsonResponse($response, ['error' => 'Invalid webhook request.'], 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (UnexpectedValueException | SignatureVerificationException $error) {
            error_log('Rejected Stripe webhook: ' . $error->getMessage());
            return $this->jsonResponse($response, ['error' => 'Invalid webhook signature or payload.'], 400);
        }

        if (!in_array($event->type, self::HANDLED_EVENTS, true)) {
            return $this->jsonResponse($response, ['received' => true]);
        }

        $session = $event->data->object;
        if (!$session instanceof Session || !is_string($session->id ?? null)) {
            error_log('Stripe webhook event did not contain a Checkout Session.');
            return $this->jsonResponse($response, ['error' => 'Invalid Checkout Session event.'], 400);
        }

        try {
            $result = (new StripeEventsModel())->getConnection()->transaction(
                function () use ($event, $session): array {
                    $paymentIntent = $session->payment_intent;
                    $customerDetails = $session->customer_details;
                    $shippingDetails = $session->shipping_details
                        ?? $session->collected_information->shipping_details
                        ?? null;
                    $now = gmdate('Y-m-d H:i:s');

                    StripeEventsModel::query()->insertOrIgnore([
                        'stripe_event_id' => $event->id,
                        'type' => $event->type,
                        'api_version' => $event->api_version,
                        'livemode' => (bool) $event->livemode,
                        'object_id' => $session->id,
                        'checkout_session_id' => $session->id,
                        'payment_intent_id' => is_string($paymentIntent)
                            ? $paymentIntent
                            : ($paymentIntent->id ?? null),
                        'customer_email' => $customerDetails->email
                            ?? $session->customer_email
                            ?? null,
                        'amount_total' => $session->amount_total,
                        'currency' => $session->currency,
                        'payment_status' => $session->payment_status,
                        'payload' => json_encode($event->toArray(), JSON_THROW_ON_ERROR),
                        'stripe_created_at' => gmdate('Y-m-d H:i:s', (int) $event->created),
                        'processing_status' => 'pending',
                        'attempts' => 0,
                    ]);

                    $eventRecord = StripeEventsModel::query()
                        ->where('stripe_event_id', $event->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (in_array($eventRecord->processing_status, ['processed', 'ignored'], true)) {
                        return ['status' => 200];
                    }

                    $eventRecord->attempts++;
                    $eventRecord->processing_status = 'pending';
                    $eventRecord->error_message = null;
                    $eventRecord->save();

                    $metadataOrderId = $session->metadata->order_id ?? null;
                    $orderId = is_string($metadataOrderId) && $metadataOrderId !== ''
                        ? $metadataOrderId
                        : $session->client_reference_id;

                    if (!is_string($orderId) || !preg_match('/^[a-f0-9]{32}$/', $orderId)) {
                        $eventRecord->processing_status = 'ignored';
                        $eventRecord->processed_at = $now;
                        $eventRecord->save();

                        return ['status' => 200];
                    }

                    $order = OrdersModel::query()->whereKey($orderId)->lockForUpdate()->first();
                    if ($order === null) {
                        $eventRecord->processing_status = 'failed';
                        $eventRecord->error_message = 'Order referenced by the Checkout Session was not found.';
                        $eventRecord->save();

                        return ['status' => 500];
                    }

                    if (
                        $order->checkout_session_id !== null
                        && $order->checkout_session_id !== $session->id
                    ) {
                        $eventRecord->processing_status = 'failed';
                        $eventRecord->error_message = 'Checkout Session ID does not match the order.';
                        $eventRecord->save();

                        return ['status' => 500];
                    }

                    $order->checkout_session_id = $session->id;
                    $order->payment_intent_id = is_string($paymentIntent)
                        ? $paymentIntent
                        : ($paymentIntent->id ?? null);
                    $order->customer_email = $customerDetails->email
                        ?? $session->customer_email
                        ?? $order->customer_email;
                    $order->amount_total = $session->amount_total ?? $order->amount_total;
                    $order->currency = $session->currency ?? $order->currency;
                    if ($shippingDetails !== null && method_exists($shippingDetails, 'toArray')) {
                        $order->shipping_address = $shippingDetails->toArray();
                    }

                    if ($order->payment_status !== 'paid') {
                        if (
                            in_array($event->type, [
                                'checkout.session.completed',
                                'checkout.session.async_payment_succeeded',
                            ], true)
                            && $session->payment_status === 'paid'
                        ) {
                            $order->payment_status = 'paid';
                        } elseif ($event->type === 'checkout.session.async_payment_failed') {
                            $order->payment_status = 'failed';
                        } elseif ($event->type === 'checkout.session.expired') {
                            $order->payment_status = 'expired';
                        }
                    }

                    $order->save();

                    $eventRecord->processing_status = 'processed';
                    $eventRecord->processed_at = $now;
                    $eventRecord->save();

                    return ['status' => 200];
                }
            );

            if ($result['status'] !== 200) {
                error_log('Stripe webhook could not be matched to and processed for an order.');
                return $this->jsonResponse($response, ['error' => 'Unable to process payment event.'], 500);
            }

            return $this->jsonResponse($response, ['received' => true]);
        } catch (Throwable $error) {
            error_log(
                'Failed to process Stripe webhook: '
                . get_class($error) . ': ' . $error->getMessage()
            );
            return $this->jsonResponse($response, ['error' => 'Unable to process payment event.'], 500);
        }
    }

    private function jsonResponse(
        Response $response,
        array $payload,
        int $status = 200
    ): Response {
        $response->getBody()->write((string) json_encode($payload));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
