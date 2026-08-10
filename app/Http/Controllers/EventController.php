<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\RoleEnum;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $events = Event::with('ticketTypes', 'organizer')->orderBy('start_time', 'asc')->get();

        return view('events.index', compact('events'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort_unless(auth()->user()->role === RoleEnum::Organizer, 403);

        return view('events.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === RoleEnum::Organizer, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'required|string|max:255',
            'status' => 'required|in:draft,published',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $event = $request->user()->events()->create($validated);

        return redirect()->route('events.show', $event)->with('success', 'Event created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event)
    {
        return view('events.show', compact('event'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        abort_unless($event->user_id === auth()->id() && auth()->user()->role === RoleEnum::Organizer, 403);

        return view('events.edit', compact('event'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event)
    {
        abort_unless($event->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'required|string|max:255',
            'status' => 'required|in:draft,published',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $event->update($validated);

        return redirect()->route('events.show', $event)->with('success', 'Event updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event)
    {
        abort_unless($event->user_id === auth()->id(), 403);

        $event->delete();

        return redirect()->route('events.index')->with('success', 'Event deleted successfully.');
    }

    public function toggleStatus(Event $event)
    {
        abort_unless($event->user_id === auth()->id(), 403);

        $event->status = $event->status === 'draft' ? 'published' : 'draft';

        $event->save();

        return redirect()->route('events.index');
    }
}
