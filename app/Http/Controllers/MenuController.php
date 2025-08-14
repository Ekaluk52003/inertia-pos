<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MenuController extends Controller
{
    /**
     * Display a listing of the menu items for a restaurant.
     */
    public function index(Restaurant $restaurant)
    {
        $this->authorize('viewAny', [Menu::class, $restaurant]);

        $menuItems = $restaurant->menuItems()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $categories = $menuItems->pluck('category')->unique()->values();

        return Inertia::render('Menu/Index', [
            'restaurant' => $restaurant,
            'menuItems' => $menuItems,
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for creating a new menu item.
     */
    public function create(Restaurant $restaurant)
    {
        $this->authorize('create', [Menu::class, $restaurant]);

        $categories = $restaurant->menuItems()
            ->pluck('category')
            ->unique()
            ->values();

        return Inertia::render('Menu/Create', [
            'restaurant' => $restaurant,
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created menu item in storage.
     */
    public function store(Request $request, Restaurant $restaurant)
    {
        $this->authorize('create', [Menu::class, $restaurant]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string|max:255',
            'is_available' => 'boolean',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'image_url' => 'nullable|url|max:2048',
            'options' => 'nullable|array',
            'options.*.name' => 'required_with:options|string|max:255',
            'options.*.required' => 'nullable|boolean',
            'options.*.multiple' => 'nullable|boolean',
            'options.*.values' => 'required_with:options.*.name|array',
            'options.*.values.*.name' => 'required|string|max:255',
            'options.*.values.*.price' => 'nullable|numeric|min:0',
        ]);

        // Process options to ensure required and multiple properties are set
        if (!empty($validated['options'])) {
            foreach ($validated['options'] as $key => $option) {
                // Set default values for required and multiple if not provided
                if (!isset($option['required'])) {
                    $validated['options'][$key]['required'] = true; // Default to required
                }
                if (!isset($option['multiple'])) {
                    $validated['options'][$key]['multiple'] = false; // Default to single selection
                }
            }
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('menu-images', 'public');
            $validated['image_path'] = $path;
        } elseif ($request->filled('image_url')) {
            $validated['image_path'] = $request->input('image_url');
        }

        $restaurant->menuItems()->create($validated);

        return redirect()->route('menu.index', $restaurant)
            ->with('success', 'Menu item created successfully.');
    }

    /**
     * Show the form for editing the specified menu item.
     */
    public function edit(Restaurant $restaurant, Menu $menu)
    {
        $this->authorize('update', $menu);

        $categories = $restaurant->menuItems()
            ->pluck('category')
            ->unique()
            ->values();

        return Inertia::render('Menu/Edit', [
            'restaurant' => $restaurant,
            'menuItem' => $menu,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified menu item in storage.
     */
    public function update(Request $request, Restaurant $restaurant, Menu $menu)
    {
        $this->authorize('update', $menu);
        
        // Debug incoming request data
        \Illuminate\Support\Facades\Log::debug('Menu update request data:', $request->all());
        \Illuminate\Support\Facades\Log::debug('Current menu is_available value: ' . ($menu->is_available ? 'true' : 'false'));

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string|max:255',
            'is_available' => 'boolean',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'image_url' => 'nullable|url|max:2048',
            'options' => 'nullable|array',
            'options.*.name' => 'required_with:options|string|max:255',
            'options.*.required' => 'nullable|boolean',
            'options.*.multiple' => 'nullable|boolean',
            'options.*.values' => 'required_with:options.*.name|array',
            'options.*.values.*.name' => 'required|string|max:255',
            'options.*.values.*.price' => 'nullable|numeric|min:0',
        ]);

        // Process options to ensure required and multiple properties are set
        if (!empty($validated['options'])) {
            foreach ($validated['options'] as $key => $option) {
                // Set default values for required and multiple if not provided
                if (!isset($option['required'])) {
                    $validated['options'][$key]['required'] = true; // Default to required
                }
                if (!isset($option['multiple'])) {
                    $validated['options'][$key]['multiple'] = false; // Default to single selection
                }
            }
        }

        if ($request->hasFile('image')) {
            // Delete old image if exists and it's not a URL
            if ($menu->image_path && !filter_var($menu->image_path, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($menu->image_path);
            }
            
            $path = $request->file('image')->store('menu-images', 'public');
            $validated['image_path'] = $path;
        } elseif ($request->filled('image_url')) {
            // If using a new image URL, update the path
            $validated['image_path'] = $request->input('image_url');
        }

        // Debug validated data before saving
        \Illuminate\Support\Facades\Log::debug('Validated data before update:', $validated);
        
        // Explicitly set is_available to ensure it's properly handled
        if (isset($validated['is_available'])) {
            $validated['is_available'] = (bool)$validated['is_available'];
            \Illuminate\Support\Facades\Log::debug('is_available after boolean conversion: ' . ($validated['is_available'] ? 'true' : 'false'));
        } else {
            // If is_available is not in the validated data, set a default
            $validated['is_available'] = false;
            \Illuminate\Support\Facades\Log::debug('is_available not in validated data, setting default: false');
        }
        
        $menu->update($validated);
        
        // Debug after update
        \Illuminate\Support\Facades\Log::debug('Menu after update, is_available: ' . ($menu->fresh()->is_available ? 'true' : 'false'));

        return redirect()->route('menu.index', $restaurant)
            ->with('success', 'Menu item updated successfully.');
    }

    /**
     * Remove the specified menu item from storage.
     */
    public function destroy(Restaurant $restaurant, Menu $menu)
    {
        $this->authorize('delete', $menu);

        // Delete image if exists
        if ($menu->image_path) {
            Storage::disk('public')->delete($menu->image_path);
        }

        $menu->delete();

        return redirect()->route('menu.index', $restaurant)
            ->with('success', 'Menu item deleted successfully.');
    }

    /**
     * Toggle the availability of a menu item.
     */
    public function toggleAvailability(Restaurant $restaurant, Menu $menu)
    {
        $this->authorize('update', $menu);

        $menu->update([
            'is_available' => !$menu->is_available,
        ]);

        return redirect()->route('menu.index', $restaurant)
            ->with('success', 'Menu item availability updated.');
    }
}
