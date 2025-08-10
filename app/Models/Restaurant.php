<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'pay_before',
        'prompt_pay_id',
        'billing',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'pay_before' => 'boolean',
        'billing' => 'json',
    ];

    /**
     * Get the owner of the restaurant.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the staff users of the restaurant.
     */
    public function staff()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the menu items of the restaurant.
     */
    public function menuItems()
    {
        return $this->hasMany(Menu::class);
    }

    /**
     * Get the orders of the restaurant.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the QR codes of the restaurant.
     */
    public function qrCodes()
    {
        return $this->hasMany(QrCode::class);
    }

    /**
     * Get the bills of the restaurant.
     */
    public function bills()
    {
        return $this->hasMany(Bill::class);
    }
}
