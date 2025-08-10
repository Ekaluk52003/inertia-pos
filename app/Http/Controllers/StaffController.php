<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class StaffController extends Controller
{
    /**
     * Display a listing of the staff for a restaurant.
     */
    public function index(Restaurant $restaurant)
    {
        $this->authorize('viewStaff', $restaurant);

        $staff = $restaurant->staff()->get();

        return Inertia::render('Staff/Index', [
            'restaurant' => $restaurant,
            'staff' => $staff,
        ]);
    }

    /**
     * Show the form for creating a new staff member.
     */
    public function create(Restaurant $restaurant)
    {
        $this->authorize('createStaff', $restaurant);

        return Inertia::render('Staff/Create', [
            'restaurant' => $restaurant,
        ]);
    }

    /**
     * Store a newly created staff member in storage.
     */
    public function store(Request $request, Restaurant $restaurant)
    {
        $this->authorize('createStaff', $restaurant);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'staff',
            'restaurant_id' => $restaurant->id,
        ]);

        return redirect()->route('staff.index', $restaurant)
            ->with('success', 'Staff member created successfully.');
    }

    /**
     * Remove the specified staff member from storage.
     */
    public function destroy(Restaurant $restaurant, User $user)
    {
        $this->authorize('deleteStaff', [$restaurant, $user]);

        // Ensure the user is a staff member of this restaurant
        if ($user->role !== 'staff' || $user->restaurant_id !== $restaurant->id) {
            abort(403, 'Unauthorized action.');
        }

        $user->delete();

        return redirect()->route('staff.index', $restaurant)
            ->with('success', 'Staff member deleted successfully.');
    }
}
