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
    'qr_code_id',
        'code',
        'total_amount',
        'is_paid',
    // 'status' removed: use is_paid and qrCode->status instead
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
    ];

    /**
     * Check if order is paid.
     */
    public function isPaid(): bool
    {
    return (bool) $this->is_paid;
    }

    /**
     * Check if order is unpaid.
     */
    public function isUnpaid(): bool
    {
    return ! $this->is_paid;
    }

    /**
     * Legacy helpers retained for compatibility with controllers/tests – these map to
     * the lifecycle-based statuses used across the app. They use status string checks
     * so tests and other code that call isActive()/isBilling() continue to work.
     */
    public function isActive(): bool
    {
        // An order is considered active when it hasn't been paid and the
        // table/qr lifecycle is not in a terminal state. Prefer the QR code
        // lifecycle where available.
        if ($this->qrCode && $this->qrCode->status) {
            return in_array($this->qrCode->status, ['active', 'open', 'pending']);
        }

        return ! $this->is_paid;
    }

    public function isBilling(): bool
    {
        if ($this->qrCode && $this->qrCode->status) {
            return $this->qrCode->status === 'billing';
        }

        // Fallback: consider billing when there is an unpaid order with a bill
        return ! $this->is_paid && $this->bill()->exists();
    }

    public function isBilled(): bool
    {
        if ($this->qrCode && $this->qrCode->status) {
            return $this->qrCode->status === 'billed';
        }

        return (bool) $this->is_paid;
    }

    public function isCompleted(): bool
    {
        // Completed means paid or explicitly completed via qr lifecycle
        if ($this->qrCode && $this->qrCode->status) {
            return in_array($this->qrCode->status, ['completed', 'checked', 'closed', 'billed']);
        }

        return (bool) $this->is_paid;
    }

    public function canRequestBill(): bool
    {
        return $this->isActive() && $this->orderItems()->exists();
    }

    public function shouldShowBillButton(): bool
    {
        return $this->isActive();
    }

    public function shouldShowInvoice(): bool
    {
        return $this->isBilling() || $this->isBilled() || $this->isCompleted();
    }

    public function shouldShowPaymentQR(): bool
    {
        return $this->isBilling() && ! $this->restaurant->pay_before;
    }

    /**
     * Get the restaurant that owns the order.
     */
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the QR code that was used to create this order (nullable for legacy orders).
     */
    public function qrCode()
    {
        return $this->belongsTo(QrCode::class, 'qr_code_id');
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
