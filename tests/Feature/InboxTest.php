<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Services\AiAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_shortcut_photo_without_message_becomes_its_own_task(): void
    {
        $user = User::factory()->create();
        $board = $this->boardFor($user);
        $this->post("/boards/{$board->id}/inbox-token");
        $board->refresh();
        $this->fakeAi([]);

        $this->post("/api/inbox/{$board->inbox_token}", ['photo' => UploadedFile::fake()->image('IMG_0042.jpg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $task = Task::sole();
        $this->assertStringStartsWith('Photo from WhatsApp', $task->title);
        $this->assertSame('To Do', $task->column->name);
        $this->assertSame('IMG_0042.jpg', $task->attachments()->sole()->filename);
    }

    public function test_photo_is_attached_to_task_read_from_message(): void
    {
        $user = User::factory()->create();
        $board = $this->boardFor($user);
        $this->fakeAi([[
            'title' => 'Check damaged boxes', 'description' => 'See photo.', 'priority' => 'high',
            'labels' => 'stock', 'due_date' => '', 'checklist' => [],
        ]]);

        $this->post("/boards/{$board->id}/inbox", [
            'message' => 'Boss: check these damaged boxes today',
            'photo' => UploadedFile::fake()->image('boxes.jpg'),
        ])->assertSessionHasNoErrors();

        $task = Task::sole();
        $this->assertSame('Check damaged boxes', $task->title);
        $this->assertSame(1, $task->attachments()->count());
    }

    public function test_shortcut_sends_photos_and_text_together_as_files(): void
    {
        $user = User::factory()->create();
        $board = $this->boardFor($user);
        $this->post("/boards/{$board->id}/inbox-token");
        $board->refresh();
        $this->fakeAi([[
            'title' => 'Fix the cracked shelf', 'description' => 'See photos.', 'priority' => 'medium',
            'labels' => 'maintenance', 'due_date' => '', 'checklist' => [],
        ]]);

        $this->post("/api/inbox/{$board->inbox_token}", [
            'message' => "IMG_0101.jpeg\nIMG_0102.jpeg\nBoss: tolong repair rak ni",
            'photo' => [
                UploadedFile::fake()->image('IMG_0101.jpeg'),
                UploadedFile::fake()->image('IMG_0102.jpeg'),
                UploadedFile::fake()->createWithContent('text.txt', 'Boss: tolong repair rak ni'),
            ],
        ], ['Accept' => 'application/json'])->assertOk();

        $task = Task::sole();
        $this->assertSame('Fix the cracked shelf', $task->title);
        $this->assertStringContainsString('> Boss: tolong repair rak ni', $task->description);
        $this->assertStringNotContainsString('IMG_0101', $task->description);
        $this->assertSame(2, $task->attachments()->count());
    }

    public function test_shortcut_sends_photos_as_base64_lines_in_json(): void
    {
        $user = User::factory()->create();
        $board = $this->boardFor($user);
        $this->post("/boards/{$board->id}/inbox-token");
        $board->refresh();
        $this->fakeAi([[
            'title' => 'Fix the cracked shelf', 'description' => 'See photo.', 'priority' => 'medium',
            'labels' => 'maintenance', 'due_date' => '', 'checklist' => [],
        ]]);
        $photo = base64_encode(UploadedFile::fake()->image('IMG_0101.jpeg')->get());
        $text = base64_encode('Boss: tolong repair rak ni');

        $this->postJson("/api/inbox/{$board->inbox_token}", [
            'message' => "IMG_0101.jpeg\nBoss: tolong repair rak ni",
            'photos' => "{$photo}\n{$text}",
        ])->assertOk();

        $task = Task::sole();
        $this->assertSame('Fix the cracked shelf', $task->title);
        $this->assertStringContainsString('> Boss: tolong repair rak ni', $task->description);
        $this->assertSame('image/jpeg', $task->attachments()->sole()->mime_type);
    }

    public function test_photo_shared_as_text_is_only_its_file_name_so_it_becomes_a_photo_task(): void
    {
        $user = User::factory()->create();
        $board = $this->boardFor($user);
        $this->post("/boards/{$board->id}/inbox-token");
        $board->refresh();

        $this->post("/api/inbox/{$board->inbox_token}", [
            'message' => 'IMG_0101.jpeg',
            'photo' => [UploadedFile::fake()->image('IMG_0101.jpeg')],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertStringStartsWith('Photo from WhatsApp', Task::sole()->title);
    }

    public function test_shortcut_needs_a_message_or_a_photo(): void
    {
        $user = User::factory()->create();
        $board = $this->boardFor($user);
        $this->post("/boards/{$board->id}/inbox-token");
        $board->refresh();

        $this->postJson("/api/inbox/{$board->inbox_token}", [])->assertJsonValidationErrors('message');
        $this->assertSame(0, Task::count());
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
