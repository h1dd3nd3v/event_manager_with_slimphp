<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdersModel extends Model
{
    protected $table = 'orders';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = [
        'order_id',
        'product_name',
        'product_format',
        'amount_total',
        'currency',
        'customer_email',
        'checkout_session_id',
        'payment_intent_id',
        'payment_status',
        'fulfillment_status',
        'shipping_address',
    ];

    protected $casts = [
        'amount_total' => 'integer',
        'shipping_address' => 'array',
    ];
}
