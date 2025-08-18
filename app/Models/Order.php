<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'restaurant_id',
        'table_number',
        'code',
        'total_amount',
        'is_paid',
        'status',
        'customer_notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_amount' => 'decimal:2',
        'is_paid' => 'boolean',
        'status' => 'string',
    ];

    /**
     * Check if order is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if order is in billing state.
     */
    public function isBilling(): bool
    {
        return $this->status === 'billing';
    }

    /**
     * Check if order is billed.
     */
    public function isBilled(): bool
    {
        return $this->status === 'billed';
    }

    /**
     * Check if order is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if order can request bill.
     */
    public function canRequestBill(): bool
    {
        return $this->status === 'active' && $this->orderItems()->exists();
    }

    /**
     * Check if should show bill button.
     */
    public function shouldShowBillButton(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if should show invoice.
     */
    public function shouldShowInvoice(): bool
    {
        return in_array($this->status, ['billing', 'billed', 'completed']);
    }

    /**
     * Check if should show payment QR code.
     */
    public function shouldShowPaymentQR(): bool
    {
        return $this->status === 'billing' && ! $this->restaurant->pay_before;
    }

    /**
     * Get the restaurant that owns the order.
     */
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the order items for this order.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the bill for this order.
     */
    public function bill()
    {
        return $this->hasOne(Bill::class);
    }

    /**
     * Get the payments for this order.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
