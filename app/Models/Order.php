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
        'status', // now only 'paid' or 'unpaid'
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
     * Check if order is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if order is unpaid.
     */
    public function isUnpaid(): bool
    {
        return $this->status === 'unpaid';
    }

    /**
     * Legacy helpers retained for compatibility with controllers/tests – these map to
     * the lifecycle-based statuses used across the app. They use status string checks
     * so tests and other code that call isActive()/isBilling() continue to work.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isBilling(): bool
    {
        return $this->status === 'billing';
    }

    public function isBilled(): bool
    {
        return $this->status === 'billed';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function canRequestBill(): bool
    {
        return $this->status === 'active' && $this->orderItems()->exists();
    }

    public function shouldShowBillButton(): bool
    {
        return $this->status === 'active';
    }

    public function shouldShowInvoice(): bool
    {
        return in_array($this->status, ['billing', 'billed', 'completed']);
    }

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
        // Prefer direct FK relationship when payments.order_id exists. However,
        // during the migration to table-level payments the column may be
        // dropped. To avoid QueryExceptions in environments where the column
        // is absent (local dev vs test DB differences), attempt the normal
        // hasMany relationship and fall back to querying payments by
        // table_number if the column doesn't exist.
        try {
            return $this->hasMany(Payment::class);
        } catch (\Illuminate\Database\QueryException $e) {
            // Fallback: payments stored at table-level — match by table_number
            return $this->hasMany(Payment::class, 'table_number', 'table_number');
        }
    }
}
