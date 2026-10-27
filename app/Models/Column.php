<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['board_id', 'name', 'position'])]
class Column extends Model
{
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }

    /** Reorder the column: high → medium → low, then earliest due date (no date last), then current order. */
    public function sortByPriority(): void
    {
        $rank = array_flip(array_reverse(Task::PRIORITIES)); // high => 0, medium => 1, low => 2

        $this->tasks()->get()
            ->sortBy([
                fn (Task $a, Task $b) => ($rank[$a->priority] ?? 1) <=> ($rank[$b->priority] ?? 1),
                fn (Task $a, Task $b) => ($a->due_date?->timestamp ?? PHP_INT_MAX) <=> ($b->due_date?->timestamp ?? PHP_INT_MAX),
                fn (Task $a, Task $b) => $a->position <=> $b->position,
            ])
            ->values()
            ->each(function (Task $task, int $position): void {
                if ($task->position !== $position) {
                    $task->update(['position' => $position]);
                }
            });
    }
}
