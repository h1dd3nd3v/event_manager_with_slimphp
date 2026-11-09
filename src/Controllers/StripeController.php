<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\StripeEventsModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Stripe\StripeClient;
use Throwable;

class StripeController
{
    public function create_checkout_session(Request $request, Response $response): Response
    {
        $secretKey = $_ENV['STRIPE_SECRET_KEY'];
        $priceId = $_ENV['STRIPE_PRICE_ID'];
        $domain = rtrim($_ENV['DOMAIN'], '/');

        $missingVariables = [];
        foreach ([
            'STRIPE_SECRET_KEY' => $secretKey,
            'STRIPE_PRICE_ID' => $priceId,
            'DOMAIN' => $domain,
        ] as $name => $value) {
            if ($value === '') {
                $missingVariables[] = $name;
            }
        }

        if ($missingVariables !== []) {
            $message = 'Missing required environment variable(s): ' . implode(', ', $missingVariables) . '.';
            error_log('Stripe Checkout configuration error: ' . $message);
            return $this->jsonResponse($response, ['error' => $message], 500);
        }

        if (
            !filter_var($domain, FILTER_VALIDATE_URL)
            || !in_array(parse_url($domain, PHP_URL_SCHEME), ['http', 'https'], true)
        ) {
            $message = 'Invalid DOMAIN: set it to an absolute http or https URL.';
            error_log('Stripe Checkout configuration error: ' . $message);
            return $this->jsonResponse($response, [
                'error' => $message,
            ], 500);
        }

        try {
            $stripe = new StripeClient($secretKey);
            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'billing_address_collection' => 'auto',
                'phone_number_collection' => [
                    'enabled' => true,
                ],
                'automatic_tax' => [
                    'enabled' => false,
                ],
                'allow_promotion_codes' => false,
                'submit_type' => 'auto',
                'shipping_address_collection' => [
                    'allowed_countries' => [
                        'CA',
                        'MX',
                        'US',
                        'AG',
                        'AI',
                        'AW',
                        'BB',
                        'BL',
                        'BM',
                        'BQ',
                        'BS',
                        'BZ',
                        'CR',
                        'CW',
                        'DM',
                        'DO',
                        'GD',
                        'GL',
                        'GP',
                        'GT',
                        'HN',
                        'HT',
                        'JM',
                        'KN',
                        'KY',
                        'LC',
                        'MF',
                        'MQ',
                        'MS',
                        'NI',
                        'PA',
                        'PM',
                        'PR',
                        'SV',
                        'SX',
                        'TC',
                        'TT',
                        'VC',
                        'VG',
                        'AR',
                        'BO',
                        'BR',
                        'BV',
                        'CL',
                        'CO',
                        'EC',
                        'FK',
                        'GF',
                        'GS',
                        'GY',
                        'PE',
                        'PY',
                        'SR',
                        'UY',
                        'VE',
                        'AD',
                        'AL',
                        'AT',
                        'AX',
                        'BA',
                        'BE',
                        'BG',
                        'BY',
                        'CH',
                        'CZ',
                        'DE',
                        'DK',
                        'EE',
                        'ES',
                        'FI',
                        'FO',
                        'FR',
                        'GB',
                        'GG',
                        'GI',
                        'GR',
                        'HR',
                        'HU',
                        'IE',
                        'IM',
                        'IS',
                        'IT',
                        'JE',
                        'LI',
                        'LT',
                        'LU',
                        'LV',
                        'MC',
                        'MD',
                        'ME',
                        'MK',
                        'MT',
                        'NL',
                        'NO',
                        'PL',
                        'PT',
                        'RO',
                        'RS',
                        'RU',
                        'SE',
                        'SI',
                        'SJ',
                        'SK',
                        'SM',
                        'UA',
                        'VA',
                        'AO',
                        'BF',
                        'BI',
                        'BJ',
                        'BW',
                        'CD',
                        'CF',
                        'CG',
                        'CI',
                        'CM',
                        'CV',
                        'DJ',
                        'DZ',
                        'EG',
                        'ER',
                        'ET',
                        'GA',
                        'GH',
                        'GM',
                        'GN',
                        'GQ',
                        'GW',
                        'IO',
                        'KE',
                        'KM',
                        'LR',
                        'LS',
                        'LY',
                        'MA',
                        'MG',
                        'ML',
                        'MR',
                        'MU',
                        'MW',
                        'MZ',
                        'NA',
                        'NE',
                        'NG',
                        'RE',
                        'RW',
                        'SC',
                        'SH',
                        'SL',
                        'SN',
                        'SO',
                        'SS',
                        'ST',
                        'SZ',
                        'TD',
                        'TF',
                        'TG',
                        'TN',
                        'TZ',
                        'UG',
                        'YT',
                        'ZA',
                        'ZM',
                        'ZW',
                    ],
                ],
                'name_collection' => [
                    'individual' => [
                        'enabled' => true,
                        'optional' => true,
                    ],
                ],
                'line_items' => [
                    [
                        'price' => $priceId,
                        'quantity' => 1,
                    ],
                ],
                'success_url' => $domain . '/checkout/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $domain . '/checkout/cancelled',
            ]);

            if (empty($session->url)) {
                throw new \RuntimeException('Stripe did not return a Checkout URL.');
            }

            try {
                StripeEventsModel::logCheckoutSession($session);
            } catch (Throwable $error) {
                error_log(
                    'Stripe Checkout session was created but could not be logged: '
                    . get_class($error) . ': ' . $error->getMessage()
                );

                return $this->jsonResponse($response, [
                    'error' => 'Checkout was created, but its record could not be saved.',
                    'session_id' => $session->id,
                ], 500);
            }

            return $this->jsonResponse($response, ['url' => $session->url]);
        } catch (Throwable $error) {
            error_log(
                'Failed to create Stripe Checkout session: '
                . get_class($error) . ': ' . $error->getMessage()
            );
            return $this->stripeErrorResponse(
                $response,
                'Unable to create the Stripe Checkout session.',
                $error
            );
        }
    }

    public function checkout_session(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $sessionId = $query['session_id'] ?? null;

        if (
            !is_string($sessionId)
            || !preg_match('/^cs_(?:test|live)_[A-Za-z0-9]+$/', $sessionId)
        ) {
            return $this->jsonResponse($response, [
                'error' => 'A valid Checkout Session ID is required.',
            ], 400);
        }

        try {
            $event = StripeEventsModel::query()
                ->where('checkout_session_id', $sessionId)
                ->first();

            if ($event === null) {
                return $this->jsonResponse($response, [
                    'error' => 'No saved record was found for this Checkout Session.',
                ], 404);
            }

            return $this->jsonResponse($response, [
                'sessionId' => $event->checkout_session_id,
                'status' => $event->payload['status'] ?? null,
                'paymentStatus' => $event->payment_status,
                'amountTotal' => $event->amount_total,
                'currency' => $event->currency,
            ]);
        } catch (Throwable $error) {
            error_log(
                'Failed to read Stripe Checkout session record: '
                . get_class($error) . ': ' . $error->getMessage()
            );
            return $this->jsonResponse($response, [
                'error' => 'Unable to read the Checkout Session record.',
            ], 500);
        }
    }

    public function checkout_events(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $page = $this->positiveInteger($query['page'] ?? null, 1);
        $perPage = min($this->positiveInteger($query['per_page'] ?? null, 20), 100);

        try {
            $events = StripeEventsModel::query()
                ->select([
                    'id',
                    'stripe_event_id',
                    'type',
                    'livemode',
                    'checkout_session_id',
                    'payment_intent_id',
                    'amount_total',
                    'currency',
                    'payment_status',
                    'stripe_created_at',
                    'processing_status',
                ])
                ->whereNotNull('checkout_session_id')
                ->orderByDesc('stripe_created_at')
                ->paginate($perPage, ['*'], 'page', $page);

            return $this->jsonResponse($response, [
                'data' => $events->items(),
                'pagination' => [
                    'currentPage' => $events->currentPage(),
                    'perPage' => $events->perPage(),
                    'total' => $events->total(),
                    'lastPage' => $events->lastPage(),
                ],
            ]);
        } catch (Throwable $error) {
            error_log(
                'Failed to read Stripe Checkout event records: '
                . get_class($error) . ': ' . $error->getMessage()
            );
            return $this->jsonResponse($response, [
                'error' => 'Unable to read Checkout event records.',
            ], 500);
        }
    }

    private function positiveInteger($value, int $default): int
    {
        if (!is_string($value) && !is_int($value)) {
            return $default;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $validated === false ? $default : $validated;
    }

    private function stripeErrorResponse(
        Response $response,
        string $message,
        Throwable $error
    ): Response {
        $payload = ['error' => $message];

        if (filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN)) {
            $payload['details'] = get_class($error) . ': ' . $error->getMessage();
        }

        return $this->jsonResponse($response, $payload, 500);
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
