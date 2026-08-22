<x-layouts.app title="Event Dashboard">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">{{ $event->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $event->venue }}</p>
        </div>
        <a href="{{ route('events.show', $event) }}"
            class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Back to Event
        </a>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-5">
            <p class="text-sm font-medium text-gray-500">Tickets Sold</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($stats['tickets_sold']) }}</p>
            <p class="mt-1 text-xs text-gray-400">Excludes cancelled orders</p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5">
            <p class="text-sm font-medium text-gray-500">Net Revenue</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($stats['net_revenue'], 2) }}</p>
            <p class="mt-1 text-xs text-gray-400">All orders less all refunds</p>
        </div>
    </div>
</x-layouts.app>
