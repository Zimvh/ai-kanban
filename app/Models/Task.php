<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['board_id', 'column_id', 'assignee_id', 'created_by', 'title', 'description', 'priority', 'labels', 'due_date', 'position'])]
class Task extends Model
{
    public const PRIORITIES = ['low', 'medium', 'high'];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('position');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->oldest();
    }

    /** @return list<string> */
    public function labelList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->labels))));
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null && $this->due_date->isPast() && ! $this->due_date->isToday();
    }
}
