<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function taskFor(User $user): Task
    {
        $this->actingAs($user)->post('/boards', ['name' => 'AI Project October 2026', 'month' => '2026-10']);
        $board = Board::latest('id')->first();
        $this->post("/columns/{$board->intakeColumn()->id}/tasks", ['title' => 'Check delivery']);

        return Task::latest('id')->first();
    }

    public function test_member_uploads_photo_that_is_shrunk_to_jpeg_and_can_be_viewed(): void
    {
        $user = User::factory()->create();
        $task = $this->taskFor($user);

        $this->post("/tasks/{$task->id}/attachments", ['photos' => [UploadedFile::fake()->image('receipt.png', 4000, 3000)]])
            ->assertSessionHasNoErrors();

        $attachment = $task->attachments()->sole();
        $this->assertSame('receipt.jpg', $attachment->filename);
        $this->assertSame('image/jpeg', $attachment->mime_type);
        [$width, $height] = getimagesizefromstring($attachment->contents());
        $this->assertSame([Attachment::MAX_SIDE, 1200], [$width, $height]);

        $this->get("/attachments/{$attachment->id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        $this->get("/tasks/{$task->id}")->assertSee(route('attachments.show', $attachment));
    }

    public function test_non_member_cannot_view_or_delete_photo(): void
    {
        $task = $this->taskFor(User::factory()->create());
        $this->post("/tasks/{$task->id}/attachments", ['photos' => [UploadedFile::fake()->image('a.jpg')]]);
        $attachment = $task->attachments()->sole();

        $this->actingAs(User::factory()->create());
        $this->get("/attachments/{$attachment->id}")->assertForbidden();
        $this->delete("/attachments/{$attachment->id}")->assertForbidden();
        $this->assertModelExists($attachment);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $task = $this->taskFor(User::factory()->create());

        $this->post("/tasks/{$task->id}/attachments", ['photos' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('photos.0');
        $this->assertSame(0, Attachment::count());
    }
}
