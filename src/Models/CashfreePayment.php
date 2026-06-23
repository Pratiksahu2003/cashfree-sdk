<?php

namespace CashfreePayment\Models;

use Illuminate\Database\Eloquent\Model;

class CashfreePayment extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cashfree_payments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'order_id',
        'transaction_id',
        'amount',
        'currency',
        'customer_id',
        'customer_phone',
        'customer_email',
        'status',
        'payment_session_id',
        'payment_method',
        'refund_status',
        'raw_response',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'raw_response' => 'array',
        'amount' => 'float',
    ];
}
