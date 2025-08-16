<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Menu extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'restaurant_id',
        'name',
        'price',
        'category',
        'is_available',
        'image_path',
        'description',
        'options',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'options' => 'array',
    ];

    /**
     * Get the restaurant that owns the menu item.
     */
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the order items for this menu item.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the image URL attribute.
     */
    protected function getImagePathAttribute($value)
    {
        if (!$value) {
            return null;
        }

        // If the path already starts with http/https, return as is
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // If the path starts with /storage, prepend the app URL
        if (str_starts_with($value, '/storage')) {
            return config('app.url') . $value;
        }

        // Remove 'public/' prefix if it exists
        $path = str_starts_with($value, 'public/') ? substr($value, 7) : $value;

        // Return the URL using Storage facade
        return Storage::url($path);
    }
}
