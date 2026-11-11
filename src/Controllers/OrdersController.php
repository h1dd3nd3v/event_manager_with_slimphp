<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\OrdersModel;
use App\Utilities\ShippingCountries;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Stripe\StripeClient;
use Throwable;

class OrdersController
{
    private const BOOK_TITLE = 'ABENA: Walk Proudly in Your Identity';

    public function create_order(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $format = is_array($body) ? ($body['format'] ?? null) : null;

        if (!is_string($format) || !in_array($format, ['ebook', 'hardcover'], true)) {
            return $this->jsonResponse($response, [
                'error' => 'format must be either "ebook" or "hardcover".',
            ], 400);
        }

        $priceVariable = $format === 'ebook'
            ? 'STRIPE_EBOOK_PRICE_ID'
            : 'STRIPE_HARDCOVER_PRICE_ID';
        $requiredVariables = [
            'STRIPE_SECRET_KEY',
            $priceVariable,
            'DOMAIN',
        ];
        $missingVariables = array_values(array_filter(
            $requiredVariables,
            static fn (string $name): bool => empty($_ENV[$name])
        ));

        if ($missingVariables !== []) {
            $message = 'Missing required environment variable(s): ' . implode(', ', $missingVariables) . '.';
            error_log('Order checkout configuration error: ' . $message);
            return $this->jsonResponse($response, ['error' => $message], 500);
        }

        $domain = rtrim($_ENV['DOMAIN'], '/');
        if (
            !filter_var($domain, FILTER_VALIDATE_URL)
            || !in_array(parse_url($domain, PHP_URL_SCHEME), ['http', 'https'], true)
        ) {
            return $this->jsonResponse($response, [
                'error' => 'Invalid DOMAIN: set it to an absolute http or https URL.',
            ], 500);
        }

        try {
            $orderId = bin2hex(random_bytes(16));
            $productName = self::BOOK_TITLE . ' (' . ucfirst($format) . ')';
            $order = OrdersModel::create([
                'order_id' => $orderId,
                'product_name' => $productName,
                'product_format' => $format,
                'payment_status' => 'pending',
                'fulfillment_status' => 'pending',
            ]);

            $sessionParameters = [
                'mode' => 'payment',
                'line_items' => [[
                    'price' => $_ENV[$priceVariable],
                    'quantity' => 1,
                ]],
                'client_reference_id' => $orderId,
                'metadata' => [
                    'order_id' => $orderId,
                    'product_format' => $format,
                ],
                'success_url' => $domain . '/checkout/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $domain . '/checkout/cancelled',
            ];

            if ($format === 'hardcover') {
                $sessionParameters['shipping_address_collection'] = [
                    'allowed_countries' => ShippingCountries::allowed(),
                ];
            }

            $stripe = new StripeClient($_ENV['STRIPE_SECRET_KEY']);
            $session = $stripe->checkout->sessions->create($sessionParameters, [
                'idempotency_key' => 'order_' . $orderId,
            ]);

            if (empty($session->url)) {
                throw new \RuntimeException('Stripe did not return a Checkout URL.');
            }

            $order->checkout_session_id = $session->id;
            $order->amount_total = $session->amount_total;
            $order->currency = $session->currency;
            $order->save();

            return $this->jsonResponse($response, [
                'orderId' => $order->order_id,
                'sessionId' => $session->id,
                'url' => $session->url,
            ], 201);
        } catch (Throwable $error) {
            if (isset($order)) {
                try {
                    $order->payment_status = 'failed';
                    $order->save();
                } catch (Throwable $statusError) {
                    error_log(
                        'Failed to mark order checkout as failed: '
                        . get_class($statusError) . ': ' . $statusError->getMessage()
                    );
                }
            }

            error_log(
                'Failed to create book order checkout: '
                . get_class($error) . ': ' . $error->getMessage()
            );
            return $this->jsonResponse($response, [
                'error' => 'Unable to create the order checkout session.',
            ], 500);
        }
    }

    public function list_orders(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $page = $this->positiveInteger($query['page'] ?? null, 1);
        $perPage = min($this->positiveInteger($query['per_page'] ?? null, 20), 100);

        try {
            $orders = OrdersModel::query()
                ->orderByDesc('created_at')
                ->paginate($perPage, ['*'], 'page', $page);

            return $this->jsonResponse($response, [
                'data' => $orders->items(),
                'pagination' => [
                    'currentPage' => $orders->currentPage(),
                    'perPage' => $orders->perPage(),
                    'total' => $orders->total(),
                    'lastPage' => $orders->lastPage(),
                ],
            ]);
        } catch (Throwable $error) {
            error_log(
                'Failed to list book orders: '
                . get_class($error) . ': ' . $error->getMessage()
            );
            return $this->jsonResponse($response, ['error' => 'Unable to list orders.'], 500);
        }
    }

    public function get_order(Request $request, Response $response, array $args): Response
    {
        $orderId = $args['order_id'] ?? '';

        if (!is_string($orderId) || !preg_match('/^[a-f0-9]{32}$/', $orderId)) {
            return $this->jsonResponse($response, ['error' => 'A valid order ID is required.'], 400);
        }

        try {
            $order = OrdersModel::find($orderId);
            if ($order === null) {
                return $this->jsonResponse($response, ['error' => 'Order not found.'], 404);
            }

            return $this->jsonResponse($response, ['data' => $order]);
        } catch (Throwable $error) {
            error_log(
                'Failed to read book order: '
                . get_class($error) . ': ' . $error->getMessage()
            );
            return $this->jsonResponse($response, ['error' => 'Unable to read the order.'], 500);
        }
    }

    public function update_fulfillment(Request $request, Response $response, array $args): Response
    {
        $orderId = $args['order_id'] ?? '';
        $body = $request->getParsedBody();
        $status = is_array($body) ? ($body['fulfillment_status'] ?? null) : null;

        if (!is_string($orderId) || !preg_match('/^[a-f0-9]{32}$/', $orderId)) {
            return $this->jsonResponse($response, ['error' => 'A valid order ID is required.'], 400);
        }

        if (
            !is_string($status)
            || !in_array($status, ['pending', 'processing', 'shipped', 'fulfilled'], true)
        ) {
            return $this->jsonResponse($response, [
                'error' => 'fulfillment_status must be pending, processing, shipped, or fulfilled.',
            ], 400);
        }

        try {
            $order = OrdersModel::find($orderId);
            if ($order === null) {
                return $this->jsonResponse($response, ['error' => 'Order not found.'], 404);
            }

            if ($order->payment_status !== 'paid') {
                return $this->jsonResponse($response, [
                    'error' => 'Fulfillment can only be updated for paid orders.',
                ], 409);
            }

            if ($order->product_format === 'ebook' && in_array($status, ['processing', 'shipped'], true)) {
                return $this->jsonResponse($response, [
                    'error' => 'E-book orders cannot use physical shipping statuses.',
                ], 400);
            }

            $order->fulfillment_status = $status;
            $order->save();

            return $this->jsonResponse($response, ['data' => $order]);
        } catch (Throwable $error) {
            error_log(
                'Failed to update order fulfillment: '
                . get_class($error) . ': ' . $error->getMessage()
            );
            return $this->jsonResponse($response, ['error' => 'Unable to update order fulfillment.'], 500);
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
