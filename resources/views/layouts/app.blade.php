<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    {{-- No build step: everything loads from a CDN. --}}
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.1.11"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .ai-text h1, .ai-text h2, .ai-text h3 { font-weight: 600; margin: .75rem 0 .25rem; }
        .ai-text h1 { font-size: 1.125rem; } .ai-text h2 { font-size: 1rem; } .ai-text h3 { font-size: .95rem; }
        .ai-text ul { list-style: disc; padding-left: 1.25rem; margin: .25rem 0; }
        .ai-text ol { list-style: decimal; padding-left: 1.25rem; margin: .25rem 0; }
        .ai-text p { margin: .35rem 0; }
        .ai-text strong { font-weight: 600; }
        .sortable-ghost { opacity: .4; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
    <header class="bg-white border-b border-slate-200">
        <div class="px-4 sm:px-6 h-14 flex items-center justify-between gap-4">
            <a href="{{ route('boards.index') }}" class="font-semibold flex items-center gap-2">
                <span class="inline-grid place-items-center w-7 h-7 rounded-md bg-indigo-600 text-white text-sm">K</span>
                {{ config('app.name') }}
            </a>
            @auth
                <div class="flex items-center gap-3 text-sm">
                    <span class="text-slate-500 hidden sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-slate-500 hover:text-slate-800">Log out</button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    <div class="px-4 sm:px-6 pt-4 space-y-2">
        @if (session('status'))
            <div class="rounded-md bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 text-sm">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-2 text-sm">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif
    </div>

    <main class="px-4 sm:px-6 py-4">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
