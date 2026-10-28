<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a forwarded WhatsApp message (and/or photos) into tasks in the board's intake column ("To Do"),
 * then sorts that column by priority. Used by the iPhone shortcut and the "Add from message" box.
 * Photos are attached to the first task; photos without a message become their own task (no AI).
 * WhatsApp's share button doesn't pass on who sent the message, so the shortcut can ask and send it as `sender`.
 */
class OrderInbox
{
    /**
     * @param  list<UploadedFile>  $photos
     * @return list<Task>
     */
    public function add(Board $board, ?string $message, ?User $user = null, array $photos = [], ?string $sender = null): array
    {
        $message = trim((string) $message);
        $sender = mb_substr(Str::squish((string) $sender), 0, 60);
        $column = $board->intakeColumn();
        $position = $column->tasks()->max('position') + 1;
        $from = $sender === '' ? '' : "**From:** {$sender}\n\n";
        $original = $message === '' ? '' : "\n\n---\n**Original message:**\n\n".collect(explode("\n", $message))->map(fn ($line) => '> '.$line)->implode("\n");
        $items = $message === '' ? [$this->photoOnly($sender)] : $this->read($board, $sender === '' ? $message : "{$sender}: {$message}");

        $created = [];
        foreach ($items as $item) {
            $task = $column->tasks()->create([
                'board_id' => $board->id,
                'created_by' => $user?->id ?? $board->owner_id,
                'title' => mb_substr($item['title'], 0, 255),
                'description' => $from.$item['description'].$original,
                'priority' => in_array($item['priority'], Task::PRIORITIES, true) ? $item['priority'] : 'medium',
                'labels' => mb_substr(collect(['whatsapp', $sender, ...explode(',', $item['labels'])])->map(fn ($l) => trim($l))->filter()->unique(fn ($l) => mb_strtolower($l))->implode(', '), 0, 255),
                'due_date' => $this->parseDate($item['due_date']),
                'position' => $position++,
            ]);

            foreach (array_values($item['checklist']) as $i => $step) {
                $task->checklist()->create(['body' => mb_substr($step, 0, 255), 'position' => $i]);
            }

            $created[] = $task;
        }

        foreach ($photos as $photo) {
            Attachment::storePhoto($created[0], $photo, $user);
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

    /** @return array{title: string, description: string, priority: string, labels: string, due_date: string, checklist: list<string>} */
    private function photoOnly(string $sender): array
    {
        return [
            'title' => 'Photo from '.($sender ?: 'WhatsApp').' · '.now()->format('j M, g:i A'),
            'description' => 'Photo shared from WhatsApp. Open the task to see it.',
            'priority' => 'medium',
            'labels' => 'photo',
            'due_date' => '',
            'checklist' => [],
        ];
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
