<?php

declare(strict_types=1);

namespace App\Controllers;

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
                        'CA', 'MX', 'US', 'AG', 'AI', 'AW', 'BB', 'BL', 'BM', 'BQ',
                        'BS', 'BZ', 'CR', 'CW', 'DM', 'DO', 'GD', 'GL', 'GP', 'GT',
                        'HN', 'HT', 'JM', 'KN', 'KY', 'LC', 'MF', 'MQ', 'MS', 'NI',
                        'PA', 'PM', 'PR', 'SV', 'SX', 'TC', 'TT', 'VC', 'VG', 'AR',
                        'BO', 'BR', 'BV', 'CL', 'CO', 'EC', 'FK', 'GF', 'GS', 'GY',
                        'PE', 'PY', 'SR', 'UY', 'VE', 'AD', 'AL', 'AT', 'AX', 'BA',
                        'BE', 'BG', 'BY', 'CH', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI',
                        'FO', 'FR', 'GB', 'GG', 'GI', 'GR', 'HR', 'HU', 'IE', 'IM',
                        'IS', 'IT', 'JE', 'LI', 'LT', 'LU', 'LV', 'MC', 'MD', 'ME',
                        'MK', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'RS', 'RU', 'SE',
                        'SI', 'SJ', 'SK', 'SM', 'UA', 'VA', 'AO', 'BF', 'BI', 'BJ',
                        'BW', 'CD', 'CF', 'CG', 'CI', 'CM', 'CV', 'DJ', 'DZ', 'EG',
                        'ER', 'ET', 'GA', 'GH', 'GM', 'GN', 'GQ', 'GW', 'IO', 'KE',
                        'KM', 'LR', 'LS', 'LY', 'MA', 'MG', 'ML', 'MR', 'MU', 'MW',
                        'MZ', 'NA', 'NE', 'NG', 'RE', 'RW', 'SC', 'SH', 'SL', 'SN',
                        'SO', 'SS', 'ST', 'SZ', 'TD', 'TF', 'TG', 'TN', 'TZ', 'UG',
                        'YT', 'ZA', 'ZM', 'ZW',
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

        $secretKey = $_ENV['STRIPE_SECRET_KEY'];

        if ($secretKey === '') {
            error_log('Stripe Checkout verification is missing STRIPE_SECRET_KEY.');
            return $this->jsonResponse($response, [
                'error' => 'Missing required environment variable: STRIPE_SECRET_KEY.',
            ], 500);
        }

        try {
            $stripe = new StripeClient($secretKey);
            $session = $stripe->checkout->sessions->retrieve($sessionId);

            return $this->jsonResponse($response, [
                'status' => $session->status,
                'paymentStatus' => $session->payment_status,
            ]);
        } catch (Throwable $error) {
            error_log(
                'Failed to verify Stripe Checkout session: '
                . get_class($error) . ': ' . $error->getMessage()
            );
            return $this->stripeErrorResponse(
                $response,
                'Unable to retrieve the Stripe Checkout session.',
                $error
            );
        }
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
