<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class QrCodeController extends Controller
{
    /**
     * Display a listing of the QR codes for a restaurant.
     */
    public function index(Restaurant $restaurant)
    {
        $this->authorize('viewAny', [QrCode::class, $restaurant]);

        $qrCodes = $restaurant->qrCodes()
            ->orderBy('table_number')
            ->get();

        return Inertia::render('QrCode/Index', [
            'restaurant' => $restaurant,
            'qrCodes' => $qrCodes,
        ]);
    }

    /**
     * Show the form for creating a new QR code.
     */
    public function create(Restaurant $restaurant)
    {
        $this->authorize('create', [QrCode::class, $restaurant]);

        return Inertia::render('QrCode/Create', [
            'restaurant' => $restaurant,
        ]);
    }

    /**
     * Store a newly created QR code in storage.
     */
    public function store(Request $request, Restaurant $restaurant)
    {
        $this->authorize('create', [QrCode::class, $restaurant]);

        $validated = $request->validate([
            'table_number' => 'required|integer|min:1',
        ]);

        // Generate a unique code for the QR code
        $validated['code'] = Str::uuid()->toString();
        $validated['is_active'] = true;

        $restaurant->qrCodes()->create($validated);

        return redirect()->route('qrcodes.index', $restaurant)
            ->with('success', 'QR code created successfully.');
    }

    /**
     * Display the specified QR code.
     */
    public function show(Restaurant $restaurant, QrCode $qrCode)
    {
        $this->authorize('view', $qrCode);

        return Inertia::render('QrCode/Show', [
            'restaurant' => $restaurant,
            'qrCode' => $qrCode,
        ]);
    }

    /**
     * Toggle the active status of a QR code.
     */
    public function toggleActive(Restaurant $restaurant, QrCode $qrCode)
    {
        $this->authorize('update', $qrCode);

        $qrCode->update([
            'is_active' => !$qrCode->is_active,
        ]);

        return redirect()->route('qrcodes.index', $restaurant)
            ->with('success', 'QR code status updated.');
    }

    /**
     * Remove the specified QR code from storage.
     */
    public function destroy(Restaurant $restaurant, QrCode $qrCode)
    {
        $this->authorize('delete', $qrCode);

        $qrCode->delete();

        return redirect()->route('qrcodes.index', $restaurant)
            ->with('success', 'QR code deleted successfully.');
    }

    /**
     * Regenerate the code for a QR code.
     */
    public function regenerate(Restaurant $restaurant, QrCode $qrCode)
    {
        $this->authorize('update', $qrCode);

        $qrCode->update([
            'code' => Str::uuid()->toString(),
        ]);

        return redirect()->route('qrcodes.show', [$restaurant, $qrCode])
            ->with('success', 'QR code regenerated successfully.');
    }
}
