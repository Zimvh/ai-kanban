# AI Kanban

A self-hosted kanban board for monthly AI projects. Unlimited members, built-in AI (Claude).

## Run it

```bash
cd ~/Sites/kanban
php artisan serve        # open http://localhost:8000
```

First time on a new machine: `composer install && cp .env.example .env && php artisan key:generate && php artisan migrate`.

## Turn on AI

1. Get an API key at https://console.anthropic.com
2. Put it in `.env`: `ANTHROPIC_API_KEY=sk-ant-...`
3. Optional: `ANTHROPIC_EFFORT=low` for faster and cheaper answers, `high` for deeper ones.

AI features:
- **AI plan** (board): describe the month's goal, get tasks with priority, due date, labels and checklist.
- **AI report / ask** (board): progress report (overdue, at-risk, who is overloaded) or ask any question about the board.
- **Break into checklist** (task): splits a task into steps.
- **Suggest approach** (task): posts advice (approach, risks, next step) as a comment.

## Team

- Teammates register at `/register`; the board owner adds them by email in **Settings**.
- Once everyone has joined, set `ALLOW_REGISTRATION=false` in `.env`.

## Monthly workflow

Settings → **Start next month** creates next month's board with the same columns and members,
and copies every task that is not in the last column (Done).

## Where things live

| What | File |
|---|---|
| Every AI prompt / Claude call | `app/Services/AiAssistant.php` |
| AI buttons (what happens with the result) | `app/Http/Controllers/AiController.php` |
| Boards, rollover | `app/Http/Controllers/BoardController.php` |
| Tasks, drag & drop | `app/Http/Controllers/TaskController.php` |
| Database tables | `database/migrations/2026_10_23_000001_create_kanban_tables.php` |
| Pages | `resources/views/` (Blade + Tailwind/Alpine/SortableJS from CDN, no npm build) |
| URLs | `routes/web.php` |

Tests: `php artisan test`. Database: SQLite file at `database/database.sqlite` (back it up by copying the file).

To change default columns: `Board::DEFAULT_COLUMNS` in `app/Models/Board.php`.
