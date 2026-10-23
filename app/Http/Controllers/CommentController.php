<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $this->authorizeBoard($task->board);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $task->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

        return back();
    }

    public function destroy(Request $request, Comment $comment)
    {
        $this->authorizeBoard($comment->task->board);
        abort_unless($comment->user_id === $request->user()->id || $comment->task->board->isOwner($request->user()), 403);
        $comment->delete();

        return back();
    }
}
