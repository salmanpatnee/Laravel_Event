<x-layouts.app title="Events">
    <div class="flex items-center justify-end">
        @can('create', App\Models\Event::class)
            <a href="{{ route('events.create') }}"
                class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                Create Event
            </a>
        @endcan
    </div>

    <div class="mt-4 overflow-hidden overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Name</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Venue</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Start Time</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">End Time</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Ticket Types</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Min Price</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Event By</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($events as $event)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $event->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $event->venue }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize {{ $event->status === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $event->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $event->start_time }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $event->end_time }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $event->ticketTypes->count() }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $event->ticketTypes->min('price') }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $event->organizer->name }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('events.show', $event) }}"
                                    class="font-medium text-gray-600 hover:text-gray-900">View</a>
                                @can('update', $event)
                                    <a href="{{ route('events.dashboard', $event) }}"
                                        class="font-medium text-gray-600 hover:text-gray-900">Dashboard</a>
                                    <a href="{{ route('events.edit', $event) }}"
                                        class="font-medium text-gray-600 hover:text-gray-900">Edit</a>
                                @endcan
                                @can('delete', $event)
                                    <form action="{{ route('events.destroy', $event) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-medium text-red-600 hover:text-red-800">
                                            Delete
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-6 text-center text-gray-500">No events found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
