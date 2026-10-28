# AI Kanban

A self-hosted kanban board for monthly AI projects. Unlimited members, built-in AI (OpenAI).

## Run it

```bash
cd ~/Documents/projects/kanban
php artisan serve        # open http://localhost:8000
```

First time on a new machine: `composer install && cp .env.example .env && php artisan key:generate && php artisan migrate`.

## Turn on AI

1. Get an API key at https://platform.openai.com/api-keys
2. Put it in `.env`: `OPENAI_API_KEY=sk-...`
3. Optional: `OPENAI_EFFORT=low` for faster and cheaper answers, `high` for deeper ones.

AI features:
- **AI plan** (board): describe the month's goal, get tasks with priority, due date, labels and checklist.
- **AI report / ask** (board): progress report (overdue, at-risk, who is overloaded) or ask any question about the board.
- **Break into checklist** (task): splits a task into steps.
- **Suggest approach** (task): posts advice (approach, risks, next step) as a comment.

## WhatsApp orders → board (iPhone)

Share a WhatsApp message to the board. It lands in **To Do**, the AI sets title, priority and due date,
and the column is re-sorted (high → medium → low, then earliest due date). Your personal WhatsApp is not
connected to anything; you just use the iPhone share button, so there is no risk to your number.
If AI is off or fails, the message is still saved as a task.

Without the phone: **📥 Add from message** on the board, paste, done. **⇅** on a column sorts it by priority.

**Set up the shortcut once** (the app must be online, see "Host it free" below):

1. On the board: **⚙ Settings → Create inbox link → Copy**. Keep this link private.
2. iPhone **Shortcuts** app → **+** → name it **Add to Kanban**.
3. Tap **ⓘ** (bottom) → turn on **Show in Share Sheet**. Tap *Receive* and keep only **Text**.
4. Add action **Get Contents of URL**:
   - URL: paste the inbox link
   - Method: **POST**, Headers: `Accept` = `application/json`
   - Request Body: **JSON**, add field `message` (Text) = **Shortcut Input**
5. Add action **Get Dictionary Value**: get *Value* for key `message` in *Contents of URL*.
6. Add action **Show Notification** with *Dictionary Value*.

**Use it:** in WhatsApp, long-press the order → **Forward** → tap the **share icon** (bottom right) →
**Add to Kanban**. A notification shows what was added. (First time: scroll the share sheet, tap
*Edit Actions* and add *Add to Kanban* to favourites.)

**Photos, or photo + text together:** change the shortcut so it takes both:

1. Top line *Receive … from Share Sheet*: tick **Text** and **Images**.
2. Add action **Base64 Encode** above *Get Contents of URL*: input **Shortcut Input**, tap **›** and set
   **Line Breaks** to **None**.
3. In *Get Contents of URL* (Request Body stays **JSON**), add a second **Text** field:
   `photos` = **Base64 Encoded**. Keep `message` = **Shortcut Input**.

(Form upload also works: `photo[]` as a **File** field = **Shortcut Input**, if your iOS lets you pick it.)

In WhatsApp, select the photo and its message together (long-press → **Forward** → tick both → **share icon**)
→ **Add to Kanban**. The AI reads the text and the photos are attached to that task. A photo alone becomes
a task called "Photo from WhatsApp". Photos are shrunk to 1600 px and stored in the database, because
Render's free disk is wiped on every deploy. You can also add photos on a task's page or in **📥 Add from message**.

## Host it free (Render + Neon)

1. **Database:** sign up at https://neon.tech → create a project → copy the connection string
   (`postgresql://…?sslmode=require`).
2. **Code:** push this repo to GitHub (private repo is fine).
3. **App:** sign up at https://render.com → **New → Blueprint** → pick the repo. It reads `render.yaml` and asks for:
   - `APP_KEY`: run `php artisan key:generate --show` here and paste the output
   - `APP_URL`: `https://<service-name>.onrender.com` (you can fill this after the first deploy and redeploy)
   - `DB_URL`: the Neon connection string
   - `OPENAI_API_KEY`: your OpenAI key
4. Open the site, register your account, create the board, then set `ALLOW_REGISTRATION=false` in Render's
   environment once your team has joined.
5. **Keep it awake:** Render's free plan sleeps after 15 minutes idle, and waking takes up to a minute
   (the shortcut may time out). Create a free monitor at https://cron-job.org (or UptimeRobot) that opens
   `https://<your-app>.onrender.com/up` every 10 minutes. The free 750 hours/month covers one app 24/7.

Every `git push` to the repo redeploys automatically. Database changes run on start.

## Team

- Teammates register at `/register`; the board owner adds them by email in **Settings**.
- Once everyone has joined, set `ALLOW_REGISTRATION=false` in `.env`.

## Monthly workflow

Settings → **Start next month** creates next month's board with the same columns and members,
and copies every task that is not in the last column (Done).

## Where things live

| What | File |
|---|---|
| Every AI prompt / OpenAI call | `app/Services/AiAssistant.php` |
| AI buttons (what happens with the result) | `app/Http/Controllers/AiController.php` |
| Boards, rollover | `app/Http/Controllers/BoardController.php` |
| Tasks, drag & drop | `app/Http/Controllers/TaskController.php` |
| WhatsApp inbox, priority sort | `app/Services/OrderInbox.php`, `app/Http/Controllers/InboxController.php`, `routes/api.php` |
| Hosting | `Dockerfile`, `docker/start.sh`, `render.yaml` |
| Database tables | `database/migrations/2026_10_23_000001_create_kanban_tables.php` |
| Pages | `resources/views/` (Blade + Tailwind/Alpine/SortableJS from CDN, no npm build) |
| URLs | `routes/web.php` |

Tests: `php artisan test`. Database: SQLite file at `database/database.sqlite` (back it up by copying the file).

To change default columns: `Board::DEFAULT_COLUMNS` in `app/Models/Board.php`.
