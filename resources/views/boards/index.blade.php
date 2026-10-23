@extends('layouts.app', ['title' => 'Boards'])

@section('content')
<div class="max-w-5xl mx-auto grid gap-6 md:grid-cols-[1fr_320px]">
    <section>
        <h1 class="text-xl font-semibold mb-4">Your boards</h1>

        @forelse ($boards->groupBy(fn ($b) => $b->month?->format('F Y') ?? 'No month') as $month => $group)
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mt-6 mb-2">{{ $month }}</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($group as $board)
                    <a href="{{ route('boards.show', $board) }}"
                       class="block bg-white rounded-lg border border-slate-200 p-4 hover:border-indigo-400 hover:shadow-sm transition">
                        <div class="font-medium">{{ $board->name }}</div>
                        @if ($board->description)
                            <p class="text-sm text-slate-500 mt-1 line-clamp-2">{{ $board->description }}</p>
                        @endif
                        <div class="text-xs text-slate-400 mt-3">{{ $board->tasks_count }} tasks · {{ $board->pivot->role }}</div>
                    </a>
                @endforeach
            </div>
        @empty
            <div class="bg-white rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">
                No boards yet. Create your first one →
            </div>
        @endforelse
    </section>

    <aside class="bg-white rounded-lg border border-slate-200 p-4 h-fit">
        <h2 class="font-semibold mb-3">New board</h2>
        <form method="POST" action="{{ route('boards.store') }}" class="space-y-3">
            @csrf
            <input name="name" placeholder="e.g. AI Project {{ now()->format('F Y') }}" required
                   value="{{ old('name') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            <label class="block text-xs text-slate-500">Month
                <input name="month" type="month" value="{{ old('month', now()->format('Y-m')) }}"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </label>
            <textarea name="description" rows="3" placeholder="What is this month's project about?"
                      class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('description') }}</textarea>
            <button class="w-full rounded-md bg-indigo-600 text-white py-2 text-sm font-medium hover:bg-indigo-700">Create board</button>
        </form>
    </aside>
</div>
@endsection
