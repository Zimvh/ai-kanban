<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Column;
use App\Models\Task;
use App\Services\OrderInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InboxController extends Controller
{
    public function __construct()
    {
        set_time_limit(120); // the AI reads the message before we answer
    }

    /** Called by the iPhone "Add to Kanban" shortcut. The secret token in the URL picks the board. */
    public function receive(Request $request, string $token, OrderInbox $inbox): JsonResponse
    {
        $board = Board::where('inbox_token', $token)->first();
        abort_if($board === null, 404, 'Unknown inbox link. Copy it again from the board settings.');

        $data = $request->validate(['message' => ['required', 'string', 'max:10000']]);
        $tasks = $inbox->add($board, $data['message']);

        return response()->json([
            'ok' => true,
            'message' => $this->summary($tasks),
            'tasks' => collect($tasks)->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
                'url' => route('tasks.show', $task),
            ]),
        ]);
    }

    /** "Add from message" box on the board: paste a WhatsApp message. */
    public function store(Request $request, Board $board, OrderInbox $inbox): RedirectResponse
    {
        $this->authorizeBoard($board);
        $data = $request->validate(['message' => ['required', 'string', 'max:10000']]);

        $tasks = $inbox->add($board, $data['message'], $request->user());

        return back()->with('status', $this->summary($tasks));
    }

    /** Create (or replace) the board's secret inbox link. Replacing it stops the old link working. */
    public function token(Board $board): RedirectResponse
    {
        $this->authorizeBoard($board, ownerOnly: true);
        $board->forceFill(['inbox_token' => Str::random(40)])->save();

        return back()->with('status', 'New WhatsApp inbox link created. Update it in your iPhone shortcut.');
    }

    public function sort(Column $column): RedirectResponse
    {
        $this->authorizeBoard($column->board);
        $column->sortByPriority();

        return back();
    }

    /** @param list<Task> $tasks */
    private function summary(array $tasks): string
    {
        $lines = collect($tasks)->map(fn (Task $task) => sprintf(
            '• %s (%s%s)',
            $task->title,
            $task->priority,
            $task->due_date ? ', due '.$task->due_date->format('j M') : '',
        ));

        return '✅ Added '.count($tasks).' '.Str::plural('task', count($tasks))." to the board:\n".$lines->implode("\n");
    }
}
