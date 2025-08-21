<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
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
        // Dynamic relation: if payments table still has order_id, use it.
        // Otherwise fall back to matching by table_number when possible so code
        // that expects an order relation keeps working without throwing.
        if (Schema::hasColumn($this->getTable(), 'order_id')) {
            return $this->belongsTo(Order::class, 'order_id');
        }

        if (Schema::hasColumn($this->getTable(), 'table_number')) {
            // Match orders by table_number (payments.table_number => orders.table_number)
            return $this->belongsTo(Order::class, 'table_number', 'table_number');
        }

        // Last-resort: return a belongsTo relation that will never match.
        return $this->belongsTo(Order::class, 'order_id');
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
