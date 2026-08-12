<x-layouts.app :title="$event->name">
    <div class="rounded-lg border border-gray-200 bg-white p-6">
        <div class="flex items-start justify-between">
            <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize {{ $event->status === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                {{ $event->status }}
            </span>
            @can('update', $event)
                <a href="{{ route('events.edit', $event) }}"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900">Edit</a>
            @endcan
        </div>

        <p class="mt-4 text-gray-700">{{ $event->description }}</p>

        <dl class="mt-6 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium text-gray-500">Venue</dt>
                <dd class="mt-0.5 text-gray-900">{{ $event->venue }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">Event By</dt>
                <dd class="mt-0.5 text-gray-900">{{ $event->organizer->name }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">Start Time</dt>
                <dd class="mt-0.5 text-gray-900">{{ $event->start_time }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">End Time</dt>
                <dd class="mt-0.5 text-gray-900">{{ $event->end_time }}</dd>
            </div>
        </dl>
    </div>

    <h2 class="mt-8 text-lg font-semibold tracking-tight text-gray-900">Ticket Types</h2>

    <div class="mt-4 overflow-hidden overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Name</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Price</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Quantity Remaining</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($event->ticketTypes as $ticketType)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $ticketType->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $ticketType->price }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $ticketType->quantity }}</td>
                        <td class="px-4 py-3">
                            @can('create', App\Models\Order::class)
                                <form method="POST" action="{{ route('orders.store') }}"
                                    class="flex items-center justify-end gap-2">
                                    @csrf
                                    <input type="hidden" name="event_id" value="{{ $event->id }}">
                                    <input type="hidden" name="ticket_type_id" value="{{ $ticketType->id }}">
                                    <input type="number" name="quantity" min="1" max="{{ $ticketType->quantity }}"
                                        value="1"
                                        class="w-16 rounded-md border border-gray-300 px-2 py-1 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500">
                                    <button type="submit"
                                        class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                                        Buy
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">No ticket types found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        <a href="{{ route('events.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
            Back to Events
        </a>
    </div>
</x-layouts.app>
