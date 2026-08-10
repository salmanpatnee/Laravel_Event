<x-layouts.app title="Register">

    <form action="{{ route('register.store') }}" method="POST">
        @csrf

        <div>
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}">
        </div>

        <div>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}">
        </div>

        <div>
            <label for="password">Password</label>
            <input type="password" id="password" name="password">
        </div>

        <div>
            <label for="password_confirmation">Confirm Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation">
        </div>

        <div>
            <label for="role">Role</label>
            <select id="role" name="role">
                <option value="">Select a role</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" {{ old('role') === $role->value ? 'selected' : '' }}>
                        {{ $role->label() }}
                    </option>
                @endforeach
            </select>
        </div>

        <button type="submit">Register</button>
    </form>

</x-layouts.app>
