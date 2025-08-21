<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'restaurant_id',
        'order_id',
        'qr_code_id',
        'code',
        'total_amount',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the restaurant that owns the bill.
     */
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the order that this bill is for.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * If bill is created for a table, it may link to multiple orders.
     */
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'bill_order');
    }

    public function qrCode()
    {
        return $this->belongsTo(QrCode::class);
    }
}
