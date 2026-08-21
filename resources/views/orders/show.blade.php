<x-layouts.app :title="'Order #' . $order->id">
    <div class="rounded-lg border border-gray-200 bg-white p-6">
        <div class="flex items-start justify-between">
            <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize {{ $order->status === App\OrderStatusEnum::Cancelled ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                {{ $order->status->label() }}
            </span>
        </div>

        <h1 class="mt-4 text-lg font-semibold tracking-tight text-gray-900">{{ $order->event->name }}</h1>

        <dl class="mt-6 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium text-gray-500">Order #</dt>
                <dd class="mt-0.5 text-gray-900">{{ $order->id }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">Total Amount</dt>
                <dd class="mt-0.5 text-gray-900">{{ $order->total_amount }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">Placed On</dt>
                <dd class="mt-0.5 text-gray-900">{{ $order->created_at }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">Tickets</dt>
                <dd class="mt-0.5 text-gray-900">{{ $order->tickets->count() }}</dd>
            </div>
        </dl>
    </div>

    <h2 class="mt-8 text-lg font-semibold tracking-tight text-gray-900">Tickets</h2>

    <div class="mt-4 overflow-hidden overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Code</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Ticket Type</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($order->tickets as $ticket)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $ticket->code }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $ticket->ticketType->name }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-4 py-6 text-center text-gray-500">No tickets found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        <form method="POST" action="{{ route('orders.cancel', $order) }}">
            @csrf
            <button type="submit" class="cursor-pointer rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-500">
                Cancel Order
            </button>
        </form>
    </div>
</x-layouts.app>
