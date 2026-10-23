<?php

namespace App\Services;

use Anthropic\Client;
use App\Models\Board;
use App\Models\Task;
use RuntimeException;

/**
 * Every Claude call in the app goes through this class.
 * To change model / effort, edit .env (ANTHROPIC_MODEL, ANTHROPIC_EFFORT).
 */
class AiAssistant
{
    private Client $client;

    public function __construct()
    {
        $key = config('services.anthropic.key');

        if (blank($key)) {
            throw new RuntimeException('AI is not configured. Add ANTHROPIC_API_KEY to your .env file.');
        }

        $this->client = new Client(apiKey: $key);
    }

    public static function enabled(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Turn a monthly goal into a list of concrete tasks.
     *
     * @return list<array{title: string, description: string, priority: string, labels: string, due_date: string, checklist: list<string>}>
     */
    public function planTasks(Board $board, string $goal): array
    {
        $month = $board->month?->format('F Y') ?? now()->format('F Y');
        $today = now()->toDateString();

        $prompt = <<<TXT
        I run a monthly AI project on a kanban board. Break this month's goal into concrete tasks.

        Board: {$board->name}
        Month: {$month}
        Today: {$today}

        Goal:
        {$goal}

        Existing tasks on the board (don't duplicate these):
        {$this->boardContext($board)}

        Give 5-12 tasks, each small enough for one person to finish in 1-3 days. For each task:
        - title: short and action-oriented
        - description: 1-3 sentences on what "done" means
        - priority: low, medium or high
        - labels: comma separated, e.g. "research, data, model, frontend, docs"
        - due_date: YYYY-MM-DD within the month, in a sensible order; empty string if unsure
        - checklist: 2-5 short steps
        TXT;

        $data = $this->json($prompt, [
            'type' => 'object',
            'properties' => [
                'tasks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'priority' => ['type' => 'string', 'enum' => Task::PRIORITIES],
                            'labels' => ['type' => 'string'],
                            'due_date' => ['type' => 'string'],
                            'checklist' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['title', 'description', 'priority', 'labels', 'due_date', 'checklist'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['tasks'],
            'additionalProperties' => false,
        ]);

        return $data['tasks'];
    }

    /**
     * Split one task into checklist steps.
     *
     * @return list<string>
     */
    public function breakDownTask(Task $task): array
    {
        $existing = $task->checklist->pluck('body')->implode("\n- ") ?: '(none)';

        $prompt = <<<TXT
        Break this kanban task into 3-8 concrete checklist steps, in order. Keep each step under 12 words.

        Project: {$task->board->name}
        Task: {$task->title}
        Description: {$task->description}
        Existing checklist (don't repeat these):
        - {$existing}
        TXT;

        $data = $this->json($prompt, [
            'type' => 'object',
            'properties' => [
                'steps' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['steps'],
            'additionalProperties' => false,
        ]);

        return $data['steps'];
    }

    /** Short suggestions for a single task (approach, risks, next step). Returned as markdown. */
    public function adviseOnTask(Task $task): string
    {
        $checklist = $task->checklist->map(fn ($i) => ($i->done ? '[x] ' : '[ ] ').$i->body)->implode("\n") ?: '(none)';

        return $this->text(<<<TXT
        I'm working on this task in my monthly AI project. Give me practical advice in under 150 words:
        a suggested approach, one or two risks to watch, and the very next step. Use short markdown bullets.

        Project: {$task->board->name}
        Task: {$task->title}
        Status column: {$task->column->name}
        Priority: {$task->priority}
        Due: {$task->due_date?->toDateString()}
        Description: {$task->description}
        Checklist:
        {$checklist}
        TXT);
    }

    /** Progress report for the board, or an answer to a question about it. Returned as markdown. */
    public function reportOnBoard(Board $board, ?string $question = null): string
    {
        $today = now()->toDateString();
        $ask = filled($question)
            ? "Answer this question about the board: {$question}"
            : 'Write a short progress report: overall status, what got done, what is at risk or overdue, '.
              'who is overloaded, and the top 3 priorities for the next few days.';

        return $this->text(<<<TXT
        You are the project assistant for a team kanban board. Today is {$today}.
        {$ask}
        Be concise and specific; refer to tasks by name. Use markdown headings and bullets.

        Board: {$board->name}
        Description: {$board->description}

        {$this->boardContext($board)}
        TXT);
    }

    /** Plain-text dump of the board that Claude reads as context. */
    private function boardContext(Board $board): string
    {
        $board->loadMissing('columns.tasks.assignee', 'columns.tasks.checklist');

        $out = '';
        foreach ($board->columns as $column) {
            $out .= "## {$column->name}\n";
            if ($column->tasks->isEmpty()) {
                $out .= "(empty)\n";
            }
            foreach ($column->tasks as $task) {
                $done = $task->checklist->where('done', true)->count();
                $out .= sprintf(
                    "- %s | priority: %s | assignee: %s | due: %s | checklist: %d/%d\n",
                    $task->title,
                    $task->priority,
                    $task->assignee?->name ?? 'unassigned',
                    $task->due_date?->toDateString() ?? 'none',
                    $done,
                    $task->checklist->count(),
                );
            }
        }

        return $out;
    }

    private function text(string $prompt): string
    {
        return $this->send($prompt);
    }

    private function json(string $prompt, array $schema): array
    {
        $text = $this->send($prompt, ['type' => 'json_schema', 'schema' => $schema]);

        return json_decode($text, true, flags: JSON_THROW_ON_ERROR);
    }

    private function send(string $prompt, ?array $format = null): string
    {
        $outputConfig = ['effort' => config('services.anthropic.effort')];
        if ($format) {
            $outputConfig['format'] = $format;
        }

        $message = $this->client->beta->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 16000,
            messages: [['role' => 'user', 'content' => $prompt]],
            outputConfig: $outputConfig,
            // If the main model declines a request, the API retries on a fallback model.
            betas: ['server-side-fallback-2026-07-01'],
            fallbacks: 'default',
        );

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('The AI declined this request. Try rephrasing it.');
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return $block->text;
            }
        }

        throw new RuntimeException('The AI returned an empty response.');
    }
}
