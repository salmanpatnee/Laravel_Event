@props([
    'title' => '',
])

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite('resources/css/app.css')
</head>

<body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
    <nav class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
            <a href="{{ route('events.index') }}" class="text-sm font-semibold tracking-tight text-gray-900">
                Lara Event
            </a>

            <div class="flex items-center gap-4 text-sm">
                @auth
                    <form action="{{ route('login.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="font-medium text-gray-600 hover:text-gray-900">
                            Logout
                        </button>
                    </form>
                @else
                    <a href="{{ route('login.create') }}" class="font-medium text-gray-600 hover:text-gray-900">
                        Login
                    </a>
                    <a href="{{ route('register.create') }}"
                        class="rounded-md bg-gray-900 px-3 py-1.5 font-medium text-white hover:bg-gray-700">
                        Register
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-5xl px-6 py-10">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">{{ $title }}</h1>

        @if ($errors->any())
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3">
                <ul class="list-inside list-disc text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @session('success')
            <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endsession

        <div class="mt-6">
            {{ $slot }}
        </div>
    </main>
</body>

</html>
