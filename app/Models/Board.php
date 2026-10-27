<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owner_id', 'name', 'description', 'month', 'ai_summary'])]
class Board extends Model
{
    public const DEFAULT_COLUMNS = ['Backlog', 'To Do', 'In Progress', 'Review', 'Done'];

    protected function casts(): array
    {
        return ['month' => 'date'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function columns(): HasMany
    {
        return $this->hasMany(Column::class)->orderBy('position');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    /** The secret URL the iPhone shortcut posts forwarded messages to. */
    public function inboxUrl(): ?string
    {
        return $this->inbox_token ? route('inbox.receive', $this->inbox_token) : null;
    }

    /** The column new / AI-generated tasks land in: "To Do" if present, else the first. */
    public function intakeColumn(): Column
    {
        return $this->columns()->where('name', 'To Do')->first() ?? $this->columns()->firstOrFail();
    }
}
