<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Services\AiAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    private function boardFor(User $user): Board
    {
        $this->actingAs($user)->post('/boards', ['name' => 'Orders October 2026', 'month' => '2026-10']);

        return Board::latest('id')->first();
    }

    /** @param list<array<string, mixed>> $tasks */
    private function fakeAi(array $tasks): void
    {
        config(['services.openai.key' => 'test-key']);

        $this->app->instance(AiAssistant::class, new class($tasks) extends AiAssistant
        {
            public function __construct(private array $tasks) {}

            public function readOrderMessage(Board $board, string $message): array
            {
                return $this->tasks;
            }
        });
    }

    public function test_shortcut_adds_order_to_intake_column_sorted_by_priority(): void
    {
        $user = User::factory()->create();
        $board = $this->boardFor($user);
        $this->post("/boards/{$board->id}/inbox-token")->assertSessionHasNoErrors();
        $board->refresh();

        $todo = $board->intakeColumn();
        $this->post("/columns/{$todo->id}/tasks", ['title' => 'Existing medium task']);

        $this->fakeAi([[
            'title' => 'Prepare 50 boxes for ABC', 'description' => 'Deliver before Friday.', 'priority' => 'high',
            'labels' => 'Encik Ali, packing', 'due_date' => '2026-10-30', 'checklist' => ['Count stock', 'Pack'],
        ]]);

        $this->postJson("/api/inbox/{$board->inbox_token}", ['message' => 'Encik Ali: siapkan 50 kotak untuk ABC sebelum Jumaat. Urgent.'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('tasks.0.priority', 'high');

        $task = Task::where('title', 'Prepare 50 boxes for ABC')->first();
        $this->assertSame('To Do', $task->column->name);
        $this->assertSame('whatsapp, Encik Ali, packing', $task->labels);
        $this->assertStringContainsString('> Encik Ali: siapkan 50 kotak', $task->description);
        $this->assertCount(2, $task->checklist);
        $this->assertSame(['Prepare 50 boxes for ABC', 'Existing medium task'], $todo->tasks()->pluck('title')->all());
    }

    public function test_unknown_token_is_rejected(): void
    {
        $this->postJson('/api/inbox/not-a-real-token', ['message' => 'hello'])->assertNotFound();
        $this->assertSame(0, Task::count());
    }

    public function test_message_is_kept_as_a_task_when_ai_fails(): void
    {
        $user = User::factory()->create();
        $board = $this->boardFor($user);
        config(['services.openai.key' => 'test-key']);
        $this->app->instance(AiAssistant::class, new class extends AiAssistant
        {
            public function __construct() {}

            public function readOrderMessage(Board $board, string $message): array
            {
                throw new RuntimeException('API down');
            }
        });

        $this->post("/boards/{$board->id}/inbox", ['message' => "Urgent: call supplier XYZ\nabout late stock"])
            ->assertSessionHasNoErrors();

        $task = Task::first();
        $this->assertSame('Urgent: call supplier XYZ', $task->title);
        $this->assertSame('high', $task->priority);
    }

    public function test_column_sorts_by_priority_then_due_date(): void
    {
        $user = User::factory()->create();
        $board = $this->boardFor($user);
        $column = $board->intakeColumn();

        foreach ([['Low', 'low', null], ['High later', 'high', '2026-10-30'], ['Medium', 'medium', null], ['High sooner', 'high', '2026-10-27']] as $i => [$title, $priority, $due]) {
            $column->tasks()->create(['board_id' => $board->id, 'title' => $title, 'priority' => $priority, 'due_date' => $due, 'position' => $i]);
        }

        $this->post("/columns/{$column->id}/sort")->assertRedirect();

        $this->assertSame(['High sooner', 'High later', 'Medium', 'Low'], $column->tasks()->pluck('title')->all());
    }

    public function test_only_owner_can_create_inbox_link(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $board = $this->boardFor($owner);
        $this->post("/boards/{$board->id}/members", ['email' => 'member@example.com']);

        $this->actingAs($member)->post("/boards/{$board->id}/inbox-token")->assertForbidden();
        $this->assertNull($board->fresh()->inbox_token);
    }
}
