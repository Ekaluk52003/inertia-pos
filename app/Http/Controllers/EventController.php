<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    /**
     * Display a listing of the events.
     *
     * @return \Inertia\Response
     */
    public function index(): Response
    {
        $events = Event::all();

        return Inertia::render('events/index', [
            'events' => $events
        ]);
    }

    /**
     * Show the form for creating a new event.
     *
     * @return \Inertia\Response
     */
    public function create(): Response
    {
        return Inertia::render('events/create');
    }

    /**
     * Store a newly created event in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'from_datetime' => 'required|date',
            'to_datetime' => 'required|date|after_or_equal:from_datetime',
            'location' => 'required|string|max:255',
        ]);

        Event::create($validated);

        return Redirect::route('events.index')->with('success', 'Event created successfully.');
    }

    /**
     * Display the specified event.
     *
     * @param  \App\Models\Event  $event
     * @return \Inertia\Response
     */
    public function show(Event $event): Response
    {
        return Inertia::render('events/show', [
            'event' => $event
        ]);
    }

    /**
     * Show the form for editing the specified event.
     *
     * @param  \App\Models\Event  $event
     * @return \Inertia\Response
     */
    public function edit(Event $event): Response
    {
        return Inertia::render('events/edit', [
            'event' => $event
        ]);
    }

    /**
     * Update the specified event in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Event  $event
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'from_datetime' => 'required|date',
            'to_datetime' => 'required|date|after_or_equal:from_datetime',
            'location' => 'required|string|max:255',
        ]);

        $event->update($validated);

        return Redirect::route('events.index')->with('success', 'Event updated successfully.');
    }

    /**
     * Remove the specified event from storage.
     *
     * @param  \App\Models\Event  $event
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return Redirect::route('events.index')->with('success', 'Event deleted successfully.');
    }
}
