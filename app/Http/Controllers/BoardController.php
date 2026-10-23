<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Services\AiAssistant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BoardController extends Controller
{
    public function index(Request $request)
    {
        $boards = $request->user()->boards()
            ->withCount('tasks')
            ->orderByDesc('month')
            ->orderBy('name')
            ->get();

        return view('boards.index', compact('boards'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['owner_id'] = $request->user()->id;

        $board = Board::create($data);
        $board->members()->attach($request->user()->id, ['role' => 'owner']);

        foreach (Board::DEFAULT_COLUMNS as $i => $name) {
            $board->columns()->create(['name' => $name, 'position' => $i]);
        }

        return redirect()->route('boards.show', $board);
    }

    public function show(Board $board)
    {
        $this->authorizeBoard($board);

        $board->load([
            'columns.tasks' => fn ($q) => $q->with('assignee')->withCount([
                'checklist',
                'checklist as checklist_done_count' => fn ($q) => $q->where('done', true),
                'comments',
            ]),
            'members',
        ]);

        return view('boards.show', [
            'board' => $board,
            'aiEnabled' => AiAssistant::enabled(),
        ]);
    }

    public function update(Request $request, Board $board)
    {
        $this->authorizeBoard($board, ownerOnly: true);
        $board->update($this->validated($request));

        return back()->with('status', 'Board updated.');
    }

    public function destroy(Board $board)
    {
        $this->authorizeBoard($board, ownerOnly: true);
        $board->delete();

        return redirect()->route('boards.index')->with('status', 'Board deleted.');
    }

    /** Create next month's board with the same columns, members and every unfinished task. */
    public function rollover(Request $request, Board $board)
    {
        $this->authorizeBoard($board, ownerOnly: true);
        $board->load('columns.tasks.checklist', 'members');

        $nextMonth = ($board->month ?? now()->startOfMonth())->copy()->addMonthNoOverflow()->startOfMonth();

        // "AI Project October 2026" becomes "AI Project November 2026"
        $name = preg_replace('/\b[A-Z][a-z]+ \d{4}\b/', $nextMonth->format('F Y'), $board->name, 1, $replaced);
        if (! $replaced) {
            $name = $board->name.' - '.$nextMonth->format('F Y');
        }

        $new = Board::create([
            'owner_id' => $board->owner_id,
            'name' => $name,
            'description' => $board->description,
            'month' => $nextMonth,
        ]);

        foreach ($board->members as $member) {
            $new->members()->attach($member->id, ['role' => $member->pivot->role]);
        }

        $lastColumn = $board->columns->last();
        foreach ($board->columns as $column) {
            $newColumn = $new->columns()->create(['name' => $column->name, 'position' => $column->position]);

            if ($column->is($lastColumn)) {
                continue; // finished work stays in the old month
            }

            foreach ($column->tasks as $task) {
                $copy = $newColumn->tasks()->create([
                    ...$task->only(['assignee_id', 'created_by', 'title', 'description', 'priority', 'labels', 'position']),
                    'board_id' => $new->id,
                ]);
                foreach ($task->checklist as $item) {
                    $copy->checklist()->create($item->only(['body', 'done', 'position']));
                }
            }
        }

        return redirect()->route('boards.show', $new)->with('status', 'Unfinished tasks were carried over to '.$nextMonth->format('F Y').'.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $data['month'] = filled($data['month'] ?? null) ? Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth() : null;

        return $data;
    }
}
