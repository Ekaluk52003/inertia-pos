<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'table_number',
        'trans_ref',
        'amount',
        'sender_name',
        'sender_display_name',
        'sending_bank',
        'restaurant_id',
        'qr_code_id',
        'status',
        'payment_details',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'payment_details' => 'json',
    ];

    /**
     * Get the order that this payment is for.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the restaurant that this payment belongs to.
     */
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the QR code that was used for this payment.
     */
    public function qrCode()
    {
        return $this->belongsTo(QrCode::class);
    }
}
