<?php

namespace App\Services;

use App\Models\Board;
use App\Models\Task;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Every OpenAI call in the app goes through this class.
 * To change model / effort, edit .env (OPENAI_MODEL, OPENAI_EFFORT).
 */
class AiAssistant
{
    private PendingRequest $client;

    public function __construct()
    {
        $key = config('services.openai.key');

        if (blank($key)) {
            throw new RuntimeException('AI is not configured. Add OPENAI_API_KEY to your .env file.');
        }

        $this->client = Http::baseUrl('https://api.openai.com/v1')
            ->withToken($key)
            ->acceptJson()
            ->timeout(120);
    }

    public static function enabled(): bool
    {
        return filled(config('services.openai.key'));
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
     * Turn a forwarded WhatsApp message (an order / instruction from a superior) into tasks.
     * Runs at low effort so the iPhone shortcut gets an answer within a few seconds.
     *
     * @return list<array{title: string, description: string, priority: string, labels: string, due_date: string, checklist: list<string>}>
     */
    public function readOrderMessage(Board $board, string $message): array
    {
        $today = now()->format('l, Y-m-d');

        $prompt = <<<TXT
        A team member forwarded this WhatsApp message to our kanban board. It is usually an order or
        instruction from a superior. Turn it into tasks. Today is {$today}.

        Message:
        """
        {$message}
        """

        Existing tasks on the board (if the message repeats one, still add it; the team will merge):
        {$this->boardContext($board)}

        Rules:
        - Usually one task per message. Only split into several tasks when the message clearly lists separate jobs.
        - title: short and action-oriented, in the same language as the message, under 80 characters
        - description: 1-3 sentences on what needs to be done, including names, quantities, places and amounts from the message
        - priority: high if it says urgent / ASAP / segera / today / tomorrow, or the deadline is within 2 days;
          low if it says no rush / when free / bila senang; otherwise medium
        - labels: comma separated; include the sender's name if the message shows it, plus a short topic
        - due_date: YYYY-MM-DD resolved from words like "tomorrow", "Friday", "end of month"; empty string if none
        - checklist: 0-5 short steps, only if the message implies several steps
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
        ], effort: 'low');

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

    /** Plain-text dump of the board that the AI reads as context. */
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

    private function json(string $prompt, array $schema, ?string $effort = null): array
    {
        $text = $this->send($prompt, [
            'type' => 'json_schema',
            'json_schema' => ['name' => 'result', 'strict' => true, 'schema' => $schema],
        ], $effort);

        return json_decode($text, true, flags: JSON_THROW_ON_ERROR);
    }

    private function send(string $prompt, ?array $format = null, ?string $effort = null): string
    {
        $payload = [
            'model' => config('services.openai.model'),
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'reasoning_effort' => $effort ?? config('services.openai.effort'),
            'max_completion_tokens' => 16000,
        ];
        if ($format) {
            $payload['response_format'] = $format;
        }

        $response = $this->client->post('/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException('The AI request failed: '.($response->json('error.message') ?? $response->status()));
        }

        $message = $response->json('choices.0.message');

        if (filled($message['refusal'] ?? null)) {
            throw new RuntimeException('The AI declined this request. Try rephrasing it.');
        }

        if (blank($message['content'] ?? null)) {
            throw new RuntimeException('The AI returned an empty response.');
        }

        return $message['content'];
    }
}
