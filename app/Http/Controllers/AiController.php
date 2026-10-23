<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Task;
use App\Services\AiAssistant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class AiController extends Controller
{
    public function __construct()
    {
        set_time_limit(300); // AI calls can take a minute or more
    }

    /** Generate tasks for the board from a goal. */
    public function plan(Request $request, Board $board)
    {
        $this->authorizeBoard($board);
        $data = $request->validate(['goal' => ['required', 'string', 'max:5000']]);

        return $this->attempt(function () use ($board, $data, $request) {
            $tasks = app(AiAssistant::class)->planTasks($board, $data['goal']);
            $column = $board->intakeColumn();
            $position = $column->tasks()->max('position') + 1;

            foreach ($tasks as $item) {
                $task = $column->tasks()->create([
                    'board_id' => $board->id,
                    'created_by' => $request->user()->id,
                    'title' => mb_substr($item['title'], 0, 255),
                    'description' => $item['description'],
                    'priority' => $item['priority'],
                    'labels' => mb_substr($item['labels'], 0, 255),
                    'due_date' => $this->parseDate($item['due_date']),
                    'position' => $position++,
                ]);

                foreach (array_values($item['checklist']) as $i => $step) {
                    $task->checklist()->create(['body' => mb_substr($step, 0, 255), 'position' => $i]);
                }
            }

            return back()->with('status', 'AI added '.count($tasks)." tasks to \"{$column->name}\".");
        });
    }

    /** Progress report, or answer a question about the board. */
    public function report(Request $request, Board $board)
    {
        $this->authorizeBoard($board);
        $data = $request->validate(['question' => ['nullable', 'string', 'max:1000']]);

        return $this->attempt(function () use ($board, $data) {
            $answer = app(AiAssistant::class)->reportOnBoard($board, $data['question'] ?? null);

            if (blank($data['question'] ?? null)) {
                $board->update(['ai_summary' => $answer]);
            }

            return back()->with('ai_answer', $answer)->with('ai_question', $data['question'] ?? null);
        });
    }

    /** Add AI-written checklist steps to a task. */
    public function breakdown(Task $task)
    {
        $this->authorizeBoard($task->board);

        return $this->attempt(function () use ($task) {
            $steps = app(AiAssistant::class)->breakDownTask($task->load('board', 'checklist'));
            $position = $task->checklist()->max('position') + 1;

            foreach ($steps as $step) {
                $task->checklist()->create(['body' => mb_substr($step, 0, 255), 'position' => $position++]);
            }

            return back()->with('status', 'AI added '.count($steps).' checklist steps.');
        });
    }

    /** Post AI advice on a task as a comment. */
    public function advise(Task $task)
    {
        $this->authorizeBoard($task->board);

        return $this->attempt(function () use ($task) {
            $advice = app(AiAssistant::class)->adviseOnTask($task->load('board', 'column', 'checklist'));
            $task->comments()->create(['body' => $advice, 'is_ai' => true]);

            return back()->with('status', 'AI advice added to the comments.');
        });
    }

    private function attempt(callable $callback)
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['ai' => 'AI error: '.$e->getMessage()]);
        }
    }

    private function parseDate(string $value): ?Carbon
    {
        try {
            return filled($value) ? Carbon::createFromFormat('Y-m-d', $value)->startOfDay() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
