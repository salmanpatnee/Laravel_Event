@props([
    'title' => '',
])

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
</head>

<body>
    <nav>
        @auth
            <form action="{{ route('login.logout') }}" method="POST">
                @csrf
                <button type="submit">Logout</button>
            </form>
        @else
            <a href="{{ route('register.create') }}">Register</a>
            <a href="{{ route('login.create') }}">Login</a>
        @endauth
    </nav>

    <h1>{{ $title }}</h1>
    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    @session('success')
        <div style="color: green;">
            {{ session('success') }}
        </div>
    @endsession

    {{ $slot }}
</body>

</html>
