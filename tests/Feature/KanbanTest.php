<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Services\AiAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanTest extends TestCase
{
    use RefreshDatabase;

    private function boardFor(User $user): Board
    {
        $this->actingAs($user)->post('/boards', ['name' => 'AI Project October 2026', 'month' => '2026-10']);

        return Board::latest('id')->first();
    }

    public function test_register_and_create_board_with_default_columns(): void
    {
        $this->post('/register', [
            'name' => 'Zimah', 'email' => 'z@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect('/boards');

        $this->post('/boards', ['name' => 'AI Project', 'month' => '2026-10'])->assertRedirect();
        $board = Board::first();

        $this->assertSame(Board::DEFAULT_COLUMNS, $board->columns->pluck('name')->all());
        $this->get("/boards/{$board->id}")->assertOk()->assertSee('To Do');
        $this->get('/boards')->assertOk()->assertSee('October 2026');
    }

    public function test_tasks_can_be_added_edited_moved_and_viewed(): void
    {
        $user = User::factory()->create();
        $board = $this->boardFor($user);
        [$backlog, $todo] = $board->columns;

        $this->post("/columns/{$backlog->id}/tasks", ['title' => 'Collect dataset']);
        $task = Task::first();

        $this->put("/tasks/{$task->id}", [
            'title' => 'Collect dataset', 'column_id' => $todo->id, 'assignee_id' => $user->id,
            'priority' => 'high', 'labels' => 'data, research', 'due_date' => '2026-10-30',
        ])->assertSessionHasNoErrors();
        $this->assertSame($todo->id, $task->fresh()->column_id);

        $this->postJson("/tasks/{$task->id}/move", ['column_id' => $backlog->id, 'order' => [$task->id]])->assertOk();
        $this->assertSame($backlog->id, $task->fresh()->column_id);

        $this->post("/tasks/{$task->id}/checklist", ['body' => 'Find sources']);
        $this->post("/tasks/{$task->id}/comments", ['body' => 'On it **today**']);
        $this->get("/tasks/{$task->id}")->assertOk()->assertSee('Find sources')->assertSee('<strong>today</strong>', false);
        $this->get("/boards/{$board->id}")->assertOk()->assertSee('Collect dataset')->assertSee('0/1');
    }

    public function test_non_members_are_blocked_and_members_can_be_added(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create(['email' => 'friend@example.com']);
        $board = $this->boardFor($owner);

        $this->actingAs($other)->get("/boards/{$board->id}")->assertForbidden();

        $this->actingAs($owner)->post("/boards/{$board->id}/members", ['email' => 'friend@example.com']);
        $this->actingAs($other)->get("/boards/{$board->id}")->assertOk();

        // members cannot manage the board
        $this->actingAs($other)->delete("/boards/{$board->id}")->assertForbidden();
    }

    public function test_rollover_copies_unfinished_tasks_to_next_month(): void
    {
        $user = User::factory()->create();
        $board = $this->boardFor($user);
        $todo = $board->columns[1];
        $done = $board->columns->last();

        $this->post("/columns/{$todo->id}/tasks", ['title' => 'Unfinished']);
        $this->post("/columns/{$done->id}/tasks", ['title' => 'Finished']);

        $this->post("/boards/{$board->id}/rollover")->assertRedirect();
        $new = Board::latest('id')->first();

        $this->assertSame('AI Project November 2026', $new->name);
        $this->assertSame('2026-11-01', $new->month->toDateString());
        $this->assertSame(['Unfinished'], $new->tasks->pluck('title')->all());
    }

    public function test_ai_plan_creates_tasks(): void
    {
        $user = User::factory()->create();
        $board = $this->boardFor($user);

        $fake = new class extends AiAssistant {
            public function __construct() {}
            public function planTasks(Board $board, string $goal): array
            {
                return [[
                    'title' => 'Prepare FAQ data', 'description' => 'Clean the PDF', 'priority' => 'high',
                    'labels' => 'data', 'due_date' => '2026-10-10', 'checklist' => ['Extract text', 'Chunk'],
                ]];
            }
        };
        $this->app->instance(AiAssistant::class, $fake);

        $this->post("/boards/{$board->id}/ai/plan", ['goal' => 'Build an FAQ chatbot'])->assertSessionHasNoErrors();

        $task = Task::first();
        $this->assertSame('Prepare FAQ data', $task->title);
        $this->assertSame('To Do', $task->column->name);
        $this->assertCount(2, $task->checklist);
    }
}
