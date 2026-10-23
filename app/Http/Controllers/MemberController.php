<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\User;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function store(Request $request, Board $board)
    {
        $this->authorizeBoard($board, ownerOnly: true);
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return back()->withErrors(['email' => "No account for {$data['email']} yet. Ask them to register first, then add them again."]);
        }

        $board->members()->syncWithoutDetaching([$user->id => ['role' => 'member']]);

        return back()->with('status', "{$user->name} was added to the board.");
    }

    public function destroy(Board $board, User $user)
    {
        $this->authorizeBoard($board, ownerOnly: true);
        abort_if($board->isOwner($user), 422, 'The owner cannot be removed.');

        $board->members()->detach($user->id);
        $board->tasks()->where('assignee_id', $user->id)->update(['assignee_id' => null]);

        return back()->with('status', "{$user->name} was removed.");
    }
}
