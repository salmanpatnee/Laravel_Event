<x-layouts.app title="Events">
    <a href="{{ route('events.create') }}">Create Event</a>

    <table border="1">
        <thead>
            <tr>
                <th>Name</th>
                <th>Venue</th>
                <th>Status</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Total Ticket Types</th>
                <th>Min Price</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($events as $event)
                <tr>
                    <td>{{ $event->name }}</td>
                    <td>{{ $event->venue }}</td>
                    <td>{{ $event->status }}</td>
                    <td>{{ $event->start_time }}</td>
                    <td>{{ $event->end_time }}</td>
                    <td>{{ $event->ticketTypes->count() }}</td>
                    <td>{{ $event->ticketTypes->min('price') }} </td>
                    <td>
                        <a href="{{ route('events.show', $event) }}">View</a>
                        <a href="{{ route('events.edit', $event) }}">Edit</a>
                        <form action="{{ route('events.destroy', $event) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No events found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-layouts.app>
