@extends('layouts.app', ['title' => $task->title])

@php $board = $task->board; @endphp

@section('content')
<div class="max-w-5xl mx-auto" x-data="{ loading: null }">
    <a href="{{ route('boards.show', $board) }}" class="text-sm text-slate-500 hover:text-slate-800">← {{ $board->name }}</a>

    <div class="grid gap-6 md:grid-cols-[1fr_280px] mt-3">
        <div class="space-y-6">
            {{-- Details --}}
            <form method="POST" action="{{ route('tasks.update', $task) }}" class="bg-white rounded-lg border border-slate-200 p-5 space-y-4">
                @csrf @method('PUT')
                <input name="title" value="{{ old('title', $task->title) }}" required
                       class="w-full text-lg font-semibold border-0 border-b border-transparent focus:border-slate-300 focus:outline-none px-0">

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                    <label class="block"><span class="text-xs text-slate-500">Status</span>
                        <select name="column_id" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                            @foreach ($board->columns as $column)
                                <option value="{{ $column->id }}" @selected($column->id === $task->column_id)>{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block"><span class="text-xs text-slate-500">Assignee</span>
                        <select name="assignee_id" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                            <option value="">Unassigned</option>
                            @foreach ($board->members as $member)
                                <option value="{{ $member->id }}" @selected($member->id === $task->assignee_id)>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block"><span class="text-xs text-slate-500">Priority</span>
                        <select name="priority" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                            @foreach (\App\Models\Task::PRIORITIES as $p)
                                <option value="{{ $p }}" @selected($p === $task->priority)>{{ ucfirst($p) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block"><span class="text-xs text-slate-500">Due date</span>
                        <input type="date" name="due_date" value="{{ old('due_date', $task->due_date?->toDateString()) }}"
                               class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                    </label>
                    <label class="block sm:col-span-2"><span class="text-xs text-slate-500">Labels (comma separated)</span>
                        <input name="labels" value="{{ old('labels', $task->labels) }}" placeholder="research, model, docs"
                               class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                    </label>
                </div>

                <label class="block"><span class="text-xs text-slate-500">Description</span>
                    <textarea name="description" rows="6" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('description', $task->description) }}</textarea>
                </label>

                <div class="flex justify-end">
                    <button class="rounded-md bg-indigo-600 text-white px-4 py-1.5 text-sm font-medium hover:bg-indigo-700">Save</button>
                </div>
            </form>

            {{-- Checklist --}}
            <section class="bg-white rounded-lg border border-slate-200 p-5">
                @php $done = $task->checklist->where('done', true)->count(); $total = $task->checklist->count(); @endphp
                <div class="flex items-center justify-between mb-2">
                    <h2 class="font-semibold">Checklist</h2>
                    @if ($total)<span class="text-xs text-slate-500">{{ $done }}/{{ $total }}</span>@endif
                </div>
                @if ($total)
                    <div class="h-1.5 rounded bg-slate-100 mb-3"><div class="h-1.5 rounded bg-emerald-500" style="width: {{ round($done / $total * 100) }}%"></div></div>
                @endif
                <ul class="space-y-1">
                    @foreach ($task->checklist as $item)
                        <li class="flex items-center gap-2 group text-sm">
                            <form method="POST" action="{{ route('checklist.toggle', $item) }}">
                                @csrf @method('PATCH')
                                <button class="w-4 h-4 rounded border {{ $item->done ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-slate-400' }} grid place-items-center text-[10px]">{{ $item->done ? '✓' : '' }}</button>
                            </form>
                            <span class="flex-1 {{ $item->done ? 'line-through text-slate-400' : '' }}">{{ $item->body }}</span>
                            <form method="POST" action="{{ route('checklist.destroy', $item) }}" class="opacity-0 group-hover:opacity-100">
                                @csrf @method('DELETE')
                                <button class="text-xs text-slate-400 hover:text-red-600">✕</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('checklist.store', $task) }}" class="mt-2">
                    @csrf
                    <input name="body" required placeholder="+ Add item" class="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm">
                </form>
            </section>

            {{-- Photos --}}
            <section class="bg-white rounded-lg border border-slate-200 p-5">
                <h2 class="font-semibold mb-3">Photos</h2>
                @if ($task->attachments->isNotEmpty())
                    <ul class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-3">
                        @foreach ($task->attachments as $attachment)
                            <li class="group relative">
                                <a href="{{ route('attachments.show', $attachment) }}" target="_blank" class="block aspect-square overflow-hidden rounded-md border border-slate-200 bg-slate-50">
                                    <img src="{{ route('attachments.show', $attachment) }}" alt="{{ $attachment->filename }}" loading="lazy" class="h-full w-full object-cover">
                                </a>
                                @if ($attachment->user_id === auth()->id() || $board->isOwner(auth()->user()))
                                    <form method="POST" action="{{ route('attachments.destroy', $attachment) }}" onsubmit="return confirm('Delete this photo?')"
                                          class="absolute top-1 right-1 opacity-0 group-hover:opacity-100">
                                        @csrf @method('DELETE')
                                        <button class="rounded bg-white/90 px-1.5 text-xs text-slate-500 hover:text-red-600">✕</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('attachments.store', $task) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                    @csrf
                    <input type="file" name="photos[]" accept="image/*" multiple required
                           class="flex-1 min-w-0 text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm">
                    <button class="rounded-md bg-slate-800 text-white px-3 py-1 text-sm">Upload</button>
                </form>
            </section>

            {{-- Comments --}}
            <section class="bg-white rounded-lg border border-slate-200 p-5">
                <h2 class="font-semibold mb-3">Comments</h2>
                <form method="POST" action="{{ route('comments.store', $task) }}" class="mb-4">
                    @csrf
                    <textarea name="body" rows="2" required placeholder="Write a comment…" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    <div class="flex justify-end mt-1"><button class="rounded-md bg-slate-800 text-white px-3 py-1 text-sm">Comment</button></div>
                </form>
                <ul class="space-y-4">
                    @forelse ($task->comments as $comment)
                        <li class="flex gap-3 text-sm">
                            @if ($comment->is_ai)
                                <span class="inline-grid place-items-center w-6 h-6 rounded-full bg-indigo-100 text-xs shrink-0">✨</span>
                            @elseif ($comment->user)
                                @include('partials.avatar', ['user' => $comment->user])
                            @endif
                            <div class="flex-1 {{ $comment->is_ai ? 'bg-indigo-50/60 border border-indigo-100 rounded-lg p-3' : '' }}">
                                <div class="text-xs text-slate-500 mb-0.5">
                                    <span class="font-medium text-slate-700">{{ $comment->is_ai ? 'AI assistant' : ($comment->user?->name ?? 'Deleted user') }}</span>
                                    · {{ $comment->created_at->diffForHumans() }}
                                    @if ($comment->user_id === auth()->id() || $board->isOwner(auth()->user()))
                                        <form method="POST" action="{{ route('comments.destroy', $comment) }}" class="inline">
                                            @csrf @method('DELETE')
                                            <button class="ml-1 text-slate-400 hover:text-red-600">delete</button>
                                        </form>
                                    @endif
                                </div>
                                <div class="ai-text">{!! Str::markdown($comment->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-slate-400">No comments yet.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        {{-- Sidebar --}}
        <aside class="space-y-4">
            <div class="bg-white rounded-lg border border-slate-200 p-4">
                <h2 class="font-semibold mb-1">✨ AI assistant</h2>
                @if ($aiEnabled)
                    <p class="text-xs text-slate-500 mb-3">Uses the title, description and checklist above. Save your edits first.</p>
                    <form method="POST" action="{{ route('ai.breakdown', $task) }}" @submit="loading = 'breakdown'">
                        @csrf
                        <button :disabled="loading" class="w-full text-left rounded-md border border-indigo-200 px-3 py-2 text-sm hover:bg-indigo-50 disabled:opacity-60">
                            <span x-show="loading !== 'breakdown'">Break into checklist steps</span><span x-show="loading === 'breakdown'">Thinking…</span>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('ai.advise', $task) }}" @submit="loading = 'advise'" class="mt-2">
                        @csrf
                        <button :disabled="loading" class="w-full text-left rounded-md border border-indigo-200 px-3 py-2 text-sm hover:bg-indigo-50 disabled:opacity-60">
                            <span x-show="loading !== 'advise'">Suggest approach, risks & next step</span><span x-show="loading === 'advise'">Thinking…</span>
                        </button>
                    </form>
                @else
                    <p class="text-xs text-slate-500">Add <code>OPENAI_API_KEY</code> to <code>.env</code> to enable.</p>
                @endif
            </div>

            <div class="text-xs text-slate-500 px-1 space-y-1">
                <div>Created {{ $task->created_at->format('M j, Y') }}</div>
                <div>Updated {{ $task->updated_at->diffForHumans() }}</div>
            </div>

            <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task?')" class="px-1">
                @csrf @method('DELETE')
                <button class="text-sm text-red-600 hover:underline">Delete task</button>
            </form>
        </aside>
    </div>
</div>
@endsection
