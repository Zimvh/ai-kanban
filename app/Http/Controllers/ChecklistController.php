<?php

namespace App\Http\Controllers;

use App\Models\ChecklistItem;
use App\Models\Task;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $this->authorizeBoard($task->board);
        $data = $request->validate(['body' => ['required', 'string', 'max:255']]);

        $task->checklist()->create([
            'body' => $data['body'],
            'position' => $task->checklist()->max('position') + 1,
        ]);

        return back();
    }

    public function toggle(ChecklistItem $item)
    {
        $this->authorizeBoard($item->task->board);
        $item->update(['done' => ! $item->done]);

        return back();
    }

    public function destroy(ChecklistItem $item)
    {
        $this->authorizeBoard($item->task->board);
        $item->delete();

        return back();
    }
}
