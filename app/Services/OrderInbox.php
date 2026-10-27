<?php

namespace App\Services;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a forwarded WhatsApp message into tasks in the board's intake column ("To Do"),
 * then sorts that column by priority. Used by the iPhone shortcut and the "Add from message" box.
 */
class OrderInbox
{
    /**
     * @return list<Task>
     */
    public function add(Board $board, string $message, ?User $user = null): array
    {
        $message = trim($message);
        $column = $board->intakeColumn();
        $position = $column->tasks()->max('position') + 1;
        $original = "\n\n---\n**Original message:**\n\n".collect(explode("\n", $message))->map(fn ($line) => '> '.$line)->implode("\n");

        $created = [];
        foreach ($this->read($board, $message) as $item) {
            $task = $column->tasks()->create([
                'board_id' => $board->id,
                'created_by' => $user?->id ?? $board->owner_id,
                'title' => mb_substr($item['title'], 0, 255),
                'description' => $item['description'].$original,
                'priority' => in_array($item['priority'], Task::PRIORITIES, true) ? $item['priority'] : 'medium',
                'labels' => mb_substr(collect(['whatsapp', ...explode(',', $item['labels'])])->map(fn ($l) => trim($l))->filter()->unique()->implode(', '), 0, 255),
                'due_date' => $this->parseDate($item['due_date']),
                'position' => $position++,
            ]);

            foreach (array_values($item['checklist']) as $i => $step) {
                $task->checklist()->create(['body' => mb_substr($step, 0, 255), 'position' => $i]);
            }

            $created[] = $task;
        }

        $column->sortByPriority();

        return $created;
    }

    /**
     * Ask the AI to read the message. Without AI (or if it fails) the whole message becomes one task,
     * so an order is never lost.
     *
     * @return list<array{title: string, description: string, priority: string, labels: string, due_date: string, checklist: list<string>}>
     */
    private function read(Board $board, string $message): array
    {
        if (AiAssistant::enabled()) {
            try {
                $tasks = app(AiAssistant::class)->readOrderMessage($board, $message);
                if ($tasks !== []) {
                    return $tasks;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        $urgent = preg_match('/\b(urgent|asap|segera|cepat|today|hari ini|now)\b/i', $message);

        return [[
            'title' => Str::limit(Str::squish(strtok($message, "\n")), 80),
            'description' => 'Added from WhatsApp.',
            'priority' => $urgent ? 'high' : 'medium',
            'labels' => '',
            'due_date' => '',
            'checklist' => [],
        ]];
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
