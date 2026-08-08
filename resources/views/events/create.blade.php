<x-layouts.app title="Create Event">

    <form action="{{ route('events.store') }}" method="POST">
        @csrf

        <div>
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}">
        </div>

        <div>
            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description') }}</textarea>
        </div>

        <div>
            <label for="venue">Venue</label>
            <input type="text" id="venue" name="venue" value="{{ old('venue') }}">
        </div>

        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="published" {{ old('status') === 'published' ? 'selected' : '' }}>Published</option>
            </select>
        </div>

        <div>
            <label for="start_time">Start Time</label>
            <input type="datetime-local" id="start_time" name="start_time" value="{{ old('start_time') }}">
        </div>

        <div>
            <label for="end_time">End Time</label>
            <input type="datetime-local" id="end_time" name="end_time" value="{{ old('end_time') }}">
        </div>

        <button type="submit">Create Event</button>
    </form>

    <a href="{{ route('events.index') }}">Back to Events</a>
</x-layouts.app>
