<x-layouts.app title="Login">

    <form action="{{ route('login.store') }}" method="POST">
        @csrf

        <div>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}">
        </div>

        <div>
            <label for="password">Password</label>
            <input type="password" id="password" name="password">
        </div>

        <button type="submit">Login</button>
    </form>

</x-layouts.app>
