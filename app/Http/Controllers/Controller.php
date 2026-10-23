<?php

namespace App\Http\Controllers;

use App\Models\Board;

abstract class Controller
{
    /** Only members of a board may see or change anything on it. */
    protected function authorizeBoard(Board $board, bool $ownerOnly = false): void
    {
        $user = auth()->user();

        abort_unless($board->hasMember($user), 403, 'You are not a member of this board.');
        abort_if($ownerOnly && ! $board->isOwner($user), 403, 'Only the board owner can do this.');
    }
}
