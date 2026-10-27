<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/boards');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Boards
    Route::resource('boards', BoardController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('boards/{board}/rollover', [BoardController::class, 'rollover'])->name('boards.rollover');

    // Members
    Route::post('boards/{board}/members', [MemberController::class, 'store'])->name('members.store');
    Route::delete('boards/{board}/members/{user}', [MemberController::class, 'destroy'])->name('members.destroy');

    // Columns
    Route::post('boards/{board}/columns', [ColumnController::class, 'store'])->name('columns.store');
    Route::put('columns/{column}', [ColumnController::class, 'update'])->name('columns.update');
    Route::delete('columns/{column}', [ColumnController::class, 'destroy'])->name('columns.destroy');

    // Tasks
    Route::post('columns/{column}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');

    // Checklist & comments
    Route::post('tasks/{task}/checklist', [ChecklistController::class, 'store'])->name('checklist.store');
    Route::patch('checklist/{item}', [ChecklistController::class, 'toggle'])->name('checklist.toggle');
    Route::delete('checklist/{item}', [ChecklistController::class, 'destroy'])->name('checklist.destroy');
    Route::post('tasks/{task}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    // WhatsApp inbox & priority sort
    Route::post('boards/{board}/inbox', [InboxController::class, 'store'])->name('inbox.store');
    Route::post('boards/{board}/inbox-token', [InboxController::class, 'token'])->name('inbox.token');
    Route::post('columns/{column}/sort', [InboxController::class, 'sort'])->name('columns.sort');

    // AI
    Route::post('boards/{board}/ai/plan', [AiController::class, 'plan'])->name('ai.plan');
    Route::post('boards/{board}/ai/report', [AiController::class, 'report'])->name('ai.report');
    Route::post('tasks/{task}/ai/breakdown', [AiController::class, 'breakdown'])->name('ai.breakdown');
    Route::post('tasks/{task}/ai/advise', [AiController::class, 'advise'])->name('ai.advise');
});
