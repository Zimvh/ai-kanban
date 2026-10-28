<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AttachmentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeBoard($task->board);
        $request->validate(['photos' => ['required', 'array', 'max:10'], 'photos.*' => ['image', 'max:10240']]);

        foreach ($request->file('photos') as $photo) {
            Attachment::storePhoto($task, $photo, $request->user());
        }

        return back();
    }

    /** Serve the photo itself. Only board members can open it. */
    public function show(Attachment $attachment): Response
    {
        $this->authorizeBoard($attachment->task->board);

        return response($attachment->contents(), 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline; filename="'.addcslashes($attachment->filename, '"\\').'"',
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        $this->authorizeBoard($attachment->task->board);
        abort_unless($attachment->user_id === $request->user()->id || $attachment->task->board->isOwner($request->user()), 403);
        $attachment->delete();

        return back();
    }
}
