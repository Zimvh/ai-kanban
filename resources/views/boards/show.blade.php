@extends('layouts.app', ['title' => $board->name])

@php
    $priorityColors = ['high' => 'bg-red-500', 'medium' => 'bg-amber-400', 'low' => 'bg-slate-300'];
    $isOwner = $board->isOwner(auth()->user());
@endphp

@section('content')
<div x-data="{ modal: {{ session('ai_answer') ? "'report'" : 'null' }}, search: '', assignee: '', loading: false }">

    {{-- Header --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="mr-auto">
            <a href="{{ route('boards.index') }}" class="text-xs text-slate-500 hover:text-slate-700">← All boards</a>
            <h1 class="text-xl font-semibold">{{ $board->name }}</h1>
            @if ($board->month)<div class="text-xs text-slate-500">{{ $board->month->format('F Y') }}</div>@endif
        </div>

        <div class="flex -space-x-1.5">
            @foreach ($board->members as $member)
                @include('partials.avatar', ['user' => $member, 'size' => 'w-7 h-7 text-[11px] ring-2 ring-white'])
            @endforeach
        </div>

        <input x-model="search" type="search" placeholder="Search tasks…"
               class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm w-40">
        <select x-model="assignee" class="rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm">
            <option value="">Everyone</option>
            <option value="{{ auth()->id() }}">My tasks</option>
            <option value="none">Unassigned</option>
            @foreach ($board->members as $member)
                @if ($member->id !== auth()->id())<option value="{{ $member->id }}">{{ $member->name }}</option>@endif
            @endforeach
        </select>

        <button @click="modal = 'inbox'" class="rounded-md bg-emerald-600 text-white px-3 py-1.5 text-sm font-medium hover:bg-emerald-700">📥 Add from message</button>
        @if ($aiEnabled)
            <button @click="modal = 'plan'" class="rounded-md bg-indigo-600 text-white px-3 py-1.5 text-sm font-medium hover:bg-indigo-700">✨ AI plan</button>
            <button @click="modal = 'report'" class="rounded-md bg-white border border-indigo-300 text-indigo-700 px-3 py-1.5 text-sm font-medium hover:bg-indigo-50">✨ AI report / ask</button>
        @endif
        <button @click="modal = 'settings'" class="rounded-md bg-white border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">⚙ Settings</button>
    </div>

    @unless ($aiEnabled)
        <div class="mb-4 rounded-md bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2 text-sm">
            AI features are off. Add <code>ANTHROPIC_API_KEY</code> to your <code>.env</code> file to turn them on.
        </div>
    @endunless

    {{-- Columns --}}
    <div class="flex gap-4 overflow-x-auto pb-4 items-start">
        @foreach ($board->columns as $column)
            <section class="bg-slate-200/70 rounded-lg w-72 shrink-0 flex flex-col max-h-[calc(100vh-11rem)]" x-data="{ editing: false }">
                <header class="px-3 pt-3 pb-2 flex items-center gap-2">
                    <template x-if="!editing">
                        <h2 class="font-semibold text-sm flex-1 cursor-text" @dblclick="editing = true" title="Double-click to rename">
                            {{ $column->name }} <span class="text-slate-500 font-normal">{{ $column->tasks->count() }}</span>
                        </h2>
                    </template>
                    <template x-if="editing">
                        <form method="POST" action="{{ route('columns.update', $column) }}" class="flex-1 flex gap-1">
                            @csrf @method('PUT')
                            <input name="name" value="{{ $column->name }}" class="flex-1 rounded border border-slate-300 px-2 py-0.5 text-sm" x-init="$el.focus()" @keydown.escape="editing = false">
                        </form>
                    </template>
                    <form method="POST" action="{{ route('columns.sort', $column) }}">
                        @csrf
                        <button class="text-slate-400 hover:text-indigo-600 text-xs" title="Sort by priority, then due date">⇅</button>
                    </form>
                    <form method="POST" action="{{ route('columns.destroy', $column) }}" onsubmit="return confirm('Delete column {{ $column->name }}?')">
                        @csrf @method('DELETE')
                        <button class="text-slate-400 hover:text-red-600 text-xs" title="Delete column">✕</button>
                    </form>
                </header>

                <div class="task-list flex-1 overflow-y-auto px-2 space-y-2 min-h-8" data-column-id="{{ $column->id }}">
                    @foreach ($column->tasks as $task)
                        <a href="{{ route('tasks.show', $task) }}" data-task-id="{{ $task->id }}"
                           data-search="{{ Str::lower($task->title.' '.$task->labels) }}"
                           data-assignee="{{ $task->assignee_id ?? 'none' }}"
                           x-show="(search === '' || $el.dataset.search.includes(search.toLowerCase())) && (assignee === '' || $el.dataset.assignee === assignee)"
                           class="block bg-white rounded-md shadow-sm border border-slate-200 p-3 hover:border-indigo-400 cursor-grab active:cursor-grabbing">
                            @if ($task->labelList())
                                <div class="flex flex-wrap gap-1 mb-1.5">
                                    @foreach ($task->labelList() as $label)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700">{{ $label }}</span>
                                    @endforeach
                                </div>
                            @endif
                            <div class="text-sm leading-snug">{{ $task->title }}</div>
                            <div class="flex items-center gap-2 mt-2 text-[11px] text-slate-500">
                                <span class="w-2 h-2 rounded-full {{ $priorityColors[$task->priority] ?? 'bg-slate-300' }}" title="{{ ucfirst($task->priority) }} priority"></span>
                                @if ($task->due_date)
                                    <span class="{{ $task->isOverdue() && ! $loop->parent->last ? 'text-red-600 font-medium' : '' }}">📅 {{ $task->due_date->format('M j') }}</span>
                                @endif
                                @if ($task->checklist_count)
                                    <span class="{{ $task->checklist_done_count === $task->checklist_count ? 'text-emerald-600' : '' }}">☑ {{ $task->checklist_done_count }}/{{ $task->checklist_count }}</span>
                                @endif
                                @if ($task->comments_count)<span>💬 {{ $task->comments_count }}</span>@endif
                                <span class="ml-auto">@if ($task->assignee)@include('partials.avatar', ['user' => $task->assignee])@endif</span>
                            </div>
                        </a>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('tasks.store', $column) }}" class="p-2">
                    @csrf
                    <input name="title" placeholder="+ Add a task" required
                           class="w-full rounded-md bg-transparent hover:bg-white focus:bg-white border border-transparent focus:border-slate-300 px-2 py-1.5 text-sm placeholder-slate-500">
                </form>
            </section>
        @endforeach

        <form method="POST" action="{{ route('columns.store', $board) }}" class="w-64 shrink-0">
            @csrf
            <input name="name" placeholder="+ Add column" required
                   class="w-full rounded-lg bg-slate-200/50 hover:bg-slate-200 focus:bg-white border border-transparent focus:border-slate-300 px-3 py-2 text-sm">
        </form>
    </div>

    {{-- Modals --}}
    <div x-show="modal" x-cloak class="fixed inset-0 z-40 bg-slate-900/40 flex items-start justify-center p-4 overflow-y-auto" @keydown.escape.window="modal = null" @click.self="modal = null">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-xl mt-12 p-6">

            {{-- Add from WhatsApp message --}}
            <div x-show="modal === 'inbox'">
                <h2 class="text-lg font-semibold">📥 Add from message</h2>
                <p class="text-sm text-slate-500 mt-1 mb-3">
                    Paste an order from WhatsApp. {{ $aiEnabled ? 'The AI picks the title, priority and due date' : 'It becomes a task' }}
                    in "{{ $board->intakeColumn()->name }}", and the column is sorted by priority.
                </p>
                <form method="POST" action="{{ route('inbox.store', $board) }}" @submit="loading = true" class="space-y-3">
                    @csrf
                    <textarea name="message" rows="6" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                              placeholder="e.g. [9:14 am, 26/10/2026] Encik Ali: Tolong siapkan 50 kotak untuk order Syarikat ABC, perlu hantar sebelum Jumaat. Urgent.">{{ old('message') }}</textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="modal = null" class="px-3 py-1.5 text-sm">Cancel</button>
                        <button :disabled="loading" class="rounded-md bg-emerald-600 text-white px-4 py-1.5 text-sm font-medium disabled:opacity-60">
                            <span x-show="!loading">Add to board</span><span x-show="loading">Reading message…</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- AI plan --}}
            <div x-show="modal === 'plan'">
                <h2 class="text-lg font-semibold">✨ Plan tasks with AI</h2>
                <p class="text-sm text-slate-500 mt-1 mb-3">Describe what you want to achieve this month. The AI adds tasks with priorities, due dates and checklists to the To Do column.</p>
                <form method="POST" action="{{ route('ai.plan', $board) }}" @submit="loading = true" class="space-y-3">
                    @csrf
                    <textarea name="goal" rows="6" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                              placeholder="e.g. Build a chatbot that answers customer questions from our FAQ PDF. Need data prep, prompt design, a simple web UI, testing and a demo at month end.">{{ old('goal', $board->description) }}</textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="modal = null" class="px-3 py-1.5 text-sm">Cancel</button>
                        <button :disabled="loading" class="rounded-md bg-indigo-600 text-white px-4 py-1.5 text-sm font-medium disabled:opacity-60">
                            <span x-show="!loading">Generate tasks</span><span x-show="loading">Thinking… (up to a minute)</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- AI report / ask --}}
            <div x-show="modal === 'report'">
                <h2 class="text-lg font-semibold">✨ AI report & questions</h2>
                <form method="POST" action="{{ route('ai.report', $board) }}" @submit="loading = true" class="flex gap-2 mt-3">
                    @csrf
                    <input name="question" placeholder="Ask about this board, or leave empty for a progress report"
                           class="flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                    <button :disabled="loading" class="rounded-md bg-indigo-600 text-white px-4 py-1.5 text-sm font-medium disabled:opacity-60">
                        <span x-show="!loading">Ask</span><span x-show="loading">Thinking…</span>
                    </button>
                </form>
                @php $answer = session('ai_answer') ?? $board->ai_summary; @endphp
                @if ($answer)
                    <div class="mt-4 rounded-lg bg-indigo-50/60 border border-indigo-100 p-4 text-sm max-h-[60vh] overflow-y-auto">
                        <div class="text-xs text-indigo-700 font-medium mb-1">
                            {{ session('ai_question') ? 'Q: '.session('ai_question') : (session('ai_answer') ? 'Progress report' : 'Last progress report') }}
                        </div>
                        <div class="ai-text">{!! Str::markdown($answer, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
                    </div>
                @endif
            </div>

            {{-- Settings --}}
            <div x-show="modal === 'settings'" class="space-y-6">
                <div>
                    <h2 class="text-lg font-semibold mb-3">Members</h2>
                    <ul class="space-y-2">
                        @foreach ($board->members as $member)
                            <li class="flex items-center gap-2 text-sm">
                                @include('partials.avatar', ['user' => $member])
                                <span>{{ $member->name }}</span>
                                <span class="text-slate-400">{{ $member->email }}</span>
                                <span class="text-xs text-slate-400 ml-auto">{{ $member->pivot->role }}</span>
                                @if ($isOwner && $member->id !== $board->owner_id)
                                    <form method="POST" action="{{ route('members.destroy', [$board, $member]) }}" onsubmit="return confirm('Remove {{ $member->name }}?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600">Remove</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($isOwner)
                        <form method="POST" action="{{ route('members.store', $board) }}" class="flex gap-2 mt-3">
                            @csrf
                            <input name="email" type="email" required placeholder="teammate@email.com" class="flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                            <button class="rounded-md bg-slate-800 text-white px-3 py-1.5 text-sm">Add member</button>
                        </form>
                        <p class="text-xs text-slate-500 mt-1">Your teammate registers at <code>{{ route('register') }}</code>, then you add them here. No member limit.</p>
                    @endif
                </div>

                @if ($isOwner)
                    <div class="space-y-2">
                        <h2 class="text-lg font-semibold">📱 WhatsApp → board (iPhone)</h2>
                        @if ($board->inboxUrl())
                            <p class="text-sm text-slate-500">Paste this secret link into your "Add to Kanban" shortcut. Anyone with the link can add tasks, so keep it private.</p>
                            <div class="flex gap-2" x-data="{ copied: false }">
                                <input readonly value="{{ $board->inboxUrl() }}" class="flex-1 rounded-md border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-mono" @focus="$el.select()">
                                <button type="button" @click="navigator.clipboard.writeText('{{ $board->inboxUrl() }}'); copied = true"
                                        class="rounded-md bg-slate-800 text-white px-3 py-1.5 text-sm" x-text="copied ? 'Copied' : 'Copy'"></button>
                            </div>
                        @else
                            <p class="text-sm text-slate-500">Create a secret link so you can share WhatsApp messages straight to this board from your iPhone.</p>
                        @endif
                        <form method="POST" action="{{ route('inbox.token', $board) }}"
                              @if ($board->inboxUrl()) onsubmit="return confirm('Make a new link? The old link will stop working.')" @endif>
                            @csrf
                            <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">{{ $board->inboxUrl() ? 'Make a new link' : 'Create inbox link' }}</button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('boards.update', $board) }}" class="space-y-2 pt-4 border-t border-slate-200">
                        @csrf @method('PUT')
                        <h2 class="text-lg font-semibold">Board details</h2>
                        <input name="name" value="{{ $board->name }}" required class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                        <input name="month" type="month" value="{{ $board->month?->format('Y-m') }}" class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                        <textarea name="description" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm">{{ $board->description }}</textarea>
                        <button class="rounded-md bg-slate-800 text-white px-3 py-1.5 text-sm">Save</button>
                    </form>

                    <div class="flex flex-wrap gap-2 pt-4 border-t border-slate-200">
                        <form method="POST" action="{{ route('boards.rollover', $board) }}" onsubmit="return confirm('Create next month\'s board and copy all unfinished tasks?')">
                            @csrf
                            <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">➜ Start next month (carry over unfinished)</button>
                        </form>
                        <form method="POST" action="{{ route('boards.destroy', $board) }}" onsubmit="return confirm('Delete this board and all its tasks? This cannot be undone.')" class="ml-auto">
                            @csrf @method('DELETE')
                            <button class="rounded-md border border-red-300 text-red-700 px-3 py-1.5 text-sm hover:bg-red-50">Delete board</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Drag & drop between columns; saves the new order of the target column.
    document.querySelectorAll('.task-list').forEach((list) => {
        new Sortable(list, {
            group: 'tasks',
            animation: 150,
            onEnd(evt) {
                const taskId = evt.item.dataset.taskId;
                const order = [...evt.to.querySelectorAll('[data-task-id]')].map((el) => el.dataset.taskId);
                fetch(`/tasks/${taskId}/move`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ column_id: evt.to.dataset.columnId, order }),
                }).then((res) => { if (!res.ok) { alert('Could not move the task. Reloading.'); location.reload(); } });
            },
        });
    });
</script>
@endpush
