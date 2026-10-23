<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Column;
use Illuminate\Http\Request;

class ColumnController extends Controller
{
    public function store(Request $request, Board $board)
    {
        $this->authorizeBoard($board);
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);

        $board->columns()->create([
            'name' => $data['name'],
            'position' => $board->columns()->max('position') + 1,
        ]);

        return back();
    }

    public function update(Request $request, Column $column)
    {
        $this->authorizeBoard($column->board);
        $column->update($request->validate(['name' => ['required', 'string', 'max:60']]));

        return back();
    }

    public function destroy(Column $column)
    {
        $this->authorizeBoard($column->board);

        if ($column->tasks()->exists()) {
            return back()->withErrors(['column' => "Move the tasks out of \"{$column->name}\" before deleting it."]);
        }

        $column->delete();

        return back();
    }
}
