<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RestaurantController extends Controller
{
    /**
     * Display a listing of the restaurants.
     */
    public function index()
    {
        $user = Auth::user();
        $restaurants = $user->ownedRestaurants;

        return Inertia::render('Restaurants/Index', [
            'restaurants' => $restaurants,
        ]);
    }

    /**
     * Show the form for creating a new restaurant.
     */
    public function create()
    {
        return Inertia::render('Restaurants/Create');
    }

    /**
     * Store a newly created restaurant in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'pay_before' => 'boolean',
            'prompt_pay_id' => 'nullable|string|max:255',
            'billing' => 'nullable|json',
        ]);

        $restaurant = Auth::user()->ownedRestaurants()->create($validated);

        return redirect()->route('restaurants.show', $restaurant)
            ->with('success', 'Restaurant created successfully.');
    }

    /**
     * Display the specified restaurant.
     */
    public function show(Restaurant $restaurant)
    {
        $this->authorize('view', $restaurant);

        return Inertia::render('Restaurants/Show', [
            'restaurant' => $restaurant,
        ]);
    }

    /**
     * Show the form for editing the specified restaurant.
     */
    public function edit(Restaurant $restaurant)
    {
        $this->authorize('update', $restaurant);

        return Inertia::render('Restaurants/Edit', [
            'restaurant' => $restaurant,
        ]);
    }

    /**
     * Update the specified restaurant in storage.
     */
    public function update(Request $request, Restaurant $restaurant)
    {
        $this->authorize('update', $restaurant);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'pay_before' => 'boolean',
            'prompt_pay_id' => 'nullable|string|max:255',
            'billing' => 'nullable|json',
        ]);

        $restaurant->update($validated);

        return redirect()->route('restaurants.show', $restaurant)
            ->with('success', 'Restaurant updated successfully.');
    }

    /**
     * Remove the specified restaurant from storage.
     */
    public function destroy(Restaurant $restaurant)
    {
        $this->authorize('delete', $restaurant);

        $restaurant->delete();

        return redirect()->route('restaurants.index')
            ->with('success', 'Restaurant deleted successfully.');
    }
}
