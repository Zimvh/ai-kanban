<?php

use App\Http\Controllers\InboxController;
use Illuminate\Support\Facades\Route;

// iPhone "Add to Kanban" shortcut posts forwarded WhatsApp messages here (no login; the token is the secret).
Route::post('inbox/{token}', [InboxController::class, 'receive'])
    ->middleware('throttle:30,1')
    ->name('inbox.receive');
