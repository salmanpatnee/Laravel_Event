<x-layouts.app :title="$event->name">

    <p><strong>Description:</strong> {{ $event->description }}</p>
    <p><strong>Venue:</strong> {{ $event->venue }}</p>
    <p><strong>Status:</strong> {{ $event->status }}</p>
    <p><strong>Start Time:</strong> {{ $event->start_time }}</p>
    <p><strong>End Time:</strong> {{ $event->end_time }}</p>
    <p><strong>Event By:</strong> {{ $event->organizer->name }}</p>

    <h2>Ticket Types</h2>
    <table border="1">
        <thead>
            <tr>
                <th>Name</th>
                <th>Price</th>
                <th>Quantity Remaining</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($event->ticketTypes as $ticketType)
                <tr>
                    <td>{{ $ticketType->name }}</td>
                    <td>{{ $ticketType->price }}</td>
                    <td>{{ $ticketType->quantity }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No ticket types found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <a href="{{ route('events.edit', $event) }}">Edit</a>
    <a href="{{ route('events.index') }}">Back to Events</a>
</x-layouts.app>
