<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Column;
use App\Models\Task;
use App\Services\OrderInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InboxController extends Controller
{
    /** Image types kept as photos. SVG is left out on purpose: it can carry scripts. */
    private const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif'];

    public function __construct()
    {
        set_time_limit(120); // the AI reads the message (and the photo is shrunk) before we answer
    }

    /** Called by the iPhone "Add to Kanban" shortcut. The secret token in the URL picks the board. */
    public function receive(Request $request, string $token, OrderInbox $inbox): JsonResponse
    {
        $board = Board::where('inbox_token', $token)->first();
        abort_if($board === null, 404, 'Unknown inbox link. Copy it again from the board settings.');

        [$message, $photos, $sender] = $this->messageAndPhotos($request);
        $tasks = $inbox->add($board, $message, photos: $photos, sender: $sender);

        return response()->json([
            'ok' => true,
            'message' => $this->summary($tasks),
            'tasks' => collect($tasks)->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
                'url' => route('tasks.show', $task),
            ]),
        ]);
    }

    /** "Add from message" box on the board: paste a WhatsApp message and/or pick photos. */
    public function store(Request $request, Board $board, OrderInbox $inbox): RedirectResponse
    {
        $this->authorizeBoard($board);
        [$message, $photos, $sender] = $this->messageAndPhotos($request);

        $tasks = $inbox->add($board, $message, $request->user(), $photos, $sender);

        return back()->with('status', $this->summary($tasks));
    }

    /** Create (or replace) the board's secret inbox link. Replacing it stops the old link working. */
    public function token(Board $board): RedirectResponse
    {
        $this->authorizeBoard($board, ownerOnly: true);
        $board->forceFill(['inbox_token' => Str::random(40)])->save();

        return back()->with('status', 'New WhatsApp inbox link created. Update it in your iPhone shortcut.');
    }

    public function sort(Column $column): RedirectResponse
    {
        $this->authorizeBoard($column->board);
        $column->sortByPriority();

        return back();
    }

    /**
     * Read the message and photos from the request. The iPhone shortcut sends whatever WhatsApp shared
     * (text, photos or both) as `message` plus either `photo` / `photo[]` files or a `photos` text field
     * of base64 items, one per line. A shared text can arrive as a .txt file or base64 item, and a photo
     * turned into text arrives as just its file name. `sender` is the name the shortcut asked for (optional).
     *
     * @return array{0: string, 1: list<UploadedFile>, 2: ?string}
     */
    private function messageAndPhotos(Request $request): array
    {
        $request->validate([
            'message' => ['nullable', 'string', 'max:10000'],
            'photo' => is_array($request->file('photo')) ? ['array', 'max:10'] : ['nullable', 'file', 'max:10240'],
            'photo.*' => ['file', 'max:10240'],
            'photos' => ['nullable', 'string', 'max:50000000'],
            'sender' => ['nullable', 'string', 'max:100'],
        ]);

        $files = [...Arr::wrap($request->file('photo')), ...$this->decodeBase64Files((string) $request->input('photos'))];
        $photos = array_values(array_filter($files, fn (UploadedFile $file) => in_array($file->getMimeType(), self::PHOTO_TYPES, true)));
        $texts = array_filter($files, fn (UploadedFile $file) => str_starts_with((string) $file->getMimeType(), 'text/plain'));

        $message = collect(preg_split('/\R/', (string) $request->input('message')))
            ->reject(fn (string $line) => preg_match('/^\s*[\w\-. ]+\.(jpe?g|png|heic|heif|gif|webp)\s*$/i', $line))
            ->implode("\n");
        if (trim($message) === '') {
            $message = collect($texts)->map(fn (UploadedFile $file) => trim($file->get()))->filter()->implode("\n\n");
        }

        if (trim($message) === '' && $photos === []) {
            throw ValidationException::withMessages(['message' => 'Send a message, a photo, or both.']);
        }

        return [mb_substr($message, 0, 10000), $photos, $request->input('sender')];
    }

    /**
     * Turn base64 items (one per line, as the shortcut's "Base64 Encode" action makes them) into temporary
     * files, so they go through the same checks as uploaded photos. Lines that aren't base64 are skipped.
     *
     * @return list<UploadedFile>
     */
    private function decodeBase64Files(string $items): array
    {
        $files = [];
        foreach (preg_split('/\s+/', trim($items), -1, PREG_SPLIT_NO_EMPTY) as $i => $item) {
            $bytes = base64_decode($item, true);
            if ($bytes === false || $bytes === '' || strlen($bytes) > 10 * 1024 * 1024 || $i >= 10) {
                continue;
            }

            $path = tempnam(sys_get_temp_dir(), 'inbox');
            file_put_contents($path, $bytes);
            app()->terminating(fn () => @unlink($path));
            $files[] = new UploadedFile($path, 'whatsapp-'.($i + 1), null, null, true);
        }

        return $files;
    }

    /** @param list<Task> $tasks */
    private function summary(array $tasks): string
    {
        $lines = collect($tasks)->map(fn (Task $task) => sprintf(
            '• %s (%s%s)',
            $task->title,
            $task->priority,
            $task->due_date ? ', due '.$task->due_date->format('j M') : '',
        ));

        return '✅ Added '.count($tasks).' '.Str::plural('task', count($tasks))." to the board:\n".$lines->implode("\n");
    }
}
