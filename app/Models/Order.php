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
    ];

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
