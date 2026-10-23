<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Column;
use App\Models\Task;
use App\Services\AiAssistant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /** Quick add from the bottom of a column. */
    public function store(Request $request, Column $column)
    {
        $this->authorizeBoard($column->board);
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $column->tasks()->create([
            'board_id' => $column->board_id,
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'position' => $column->tasks()->max('position') + 1,
        ]);

        return back();
    }

    public function show(Task $task)
    {
        $this->authorizeBoard($task->board);
        $task->load('board.members', 'board.columns', 'column', 'assignee', 'checklist', 'comments.user');

        return view('tasks.show', [
            'task' => $task,
            'aiEnabled' => AiAssistant::enabled(),
        ]);
    }

    public function update(Request $request, Task $task)
    {
        $board = $task->board;
        $this->authorizeBoard($board);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'column_id' => ['required', Rule::exists('columns', 'id')->where('board_id', $board->id)],
            'assignee_id' => ['nullable', Rule::exists('board_user', 'user_id')->where('board_id', $board->id)],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'labels' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
        ]);

        if ((int) $data['column_id'] !== $task->column_id) {
            $data['position'] = Task::where('column_id', $data['column_id'])->max('position') + 1;
        }

        $task->update($data);

        return back()->with('status', 'Task saved.');
    }

    public function destroy(Task $task)
    {
        $board = $task->board;
        $this->authorizeBoard($board);
        $task->delete();

        return redirect()->route('boards.show', $board)->with('status', 'Task deleted.');
    }

    /** Called by drag & drop: puts the task in a column and saves the new order of that column. */
    public function move(Request $request, Task $task)
    {
        $board = $task->board;
        $this->authorizeBoard($board);

        $data = $request->validate([
            'column_id' => ['required', Rule::exists('columns', 'id')->where('board_id', $board->id)],
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        DB::transaction(function () use ($task, $data, $board) {
            $task->update(['column_id' => $data['column_id']]);

            foreach ($data['order'] as $position => $id) {
                Task::where('id', $id)->where('board_id', $board->id)
                    ->update(['column_id' => $data['column_id'], 'position' => $position]);
            }
        });

        return response()->json(['ok' => true]);
    }
}
