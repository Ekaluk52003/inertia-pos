<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('restaurant.{restaurantId}', function ($user, $restaurantId) {
    Log::info('Channel Authorization Check', [
        'user_id' => $user->id,
        'user_restaurant_id' => $user->restaurant_id,
        'requested_restaurant_id' => $restaurantId,
        'authorized' => (int) $user->restaurant_id === (int) $restaurantId
    ]);
    
    // also allow user with id 1
    if ((int) $user->id === 1) {
        return true;
    }

    
    
    // only the log in user who has resturant_id same with restaurantId then true meaning that we authorize
    return (int) $user->restaurant_id === (int) $restaurantId;
});


// Broadcast::channel('delivery.{restaurantId}', function ($user) {
//     return (int) $user->id === 1;
// });


