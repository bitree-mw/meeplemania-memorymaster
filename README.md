# Meeple Mania Games

Five browser memory games on one platform, each playable three ways, with server-verified scores, leaderboards, streaks, achievements, online presence and an admin dashboard.

**Stack:** Laravel 13 · PHP 8.4 · MySQL 8+ · Inertia.js 3 · Vue 3 (Composition API, JavaScript) · Tailwind CSS 4 · Lucide icons · Web Audio API. Node is only used for Vite.

Visual direction comes from `uibuilder/` (left untouched). Design tokens live in `resources/css/app.css`.

## The games

| Game | Skill | A round |
|---|---|---|
| **Memory Master** | Sequence recall | Tiles flash in order; repeat the sequence. Each level extends the previous sequence. |
| **Meeple Pairs** | Location memory | The board is shown face-up, then flipped; match every pair within a mismatch budget. |
| **Pattern Peek** | Spatial memory | Cells of a grid light up together; tap all of them, any order. |
| **Dice Digits** | Number memory | A dice roll is shown briefly; enter the values in order on the dice keypad. |
| **Spot the Swap** | Change detection | Tokens are shown, the board blinks, one token changes colour or shape; find it. |

Every game has three modes:

- **Level Builder** — progressive levels with lives (Memory Master 1, others 3). A lost round costs a life and gives a fresh puzzle at the same level.
- **Timed Rush** — choose **30, 45 or 60 seconds**; difficulty rises every few rounds; misses cost points. Each duration has its own leaderboard.
- **Try Once** — one daily challenge per game, identical for every player; one ranked attempt per player per game per day (account required). Later runs are practice.

All difficulty, scoring, lives and timing values are in `config/games.php` and editable per game in **Admin → Settings**.

## Setup

```bash
composer install
npm install
cp .env.example .env            # set DB_* for MySQL
php artisan key:generate
php artisan migrate --seed      # games, modes, achievements, first season (no fake players)
npm run build                   # or: npm run dev
php artisan games:create-admin admin you@example.com   # prompts for the password (stored hashed)
php artisan games:make-admin you@example.com          # or promote an existing account
```

With Laravel Herd the site is served at `http://meeplemania-memorymaster.test`; otherwise `php artisan serve`. For production also run `php artisan optimize`.

Scheduler (`php artisan schedule:work` locally, cron in production):

| Command | When | Purpose |
|---|---|---|
| `games:expire-sessions` | every 5 min | Close idle runs and Timed Rush runs past their clock (verified points are kept) |
| `games:daily-rollover` | hourly, idempotent | Create today's Try Once challenges, award yesterday's badges, snapshot leaderboards |
| `games:prune-presence` | hourly | Delete stale presence rows |
| `games:prune-reset-links` | hourly | Delete expired password reset links |

## Accounts and administration

- **Sign-in** accepts a username or an email. Admins sign in at `/admin/login` (non-admin accounts are refused there).
- **Admins** are capped at 5 accounts (`User::MAX_ADMINS`, deactivated ones included). Create them in **Admin → Admins** or with `games:create-admin`, or promote an existing account from its page in **Admin → Players**; revoke access there too.
- **Monitoring:** admin sign-ins, failed or refused admin sign-ins, and every change made in the admin area are recorded in `admin_activity_logs` and shown in **Admin → Admins**.
- **Password reset links** look like `APP_URL/reset-password/{username}/{64-char secret}`. Only a SHA-256 hash of the secret is stored; a link works once, expires after 60 minutes (`auth.passwords.users.expire`), is cancelled by a newer link, and resetting signs the account out everywhere. Players request one from `/forgot-password`; admins can send one from a player's page. Mail goes through the `MAIL_*` settings (`log` locally, so links land in `storage/logs/laravel.log`).
- **Districts:** players choose one of Malawi's 28 districts when signing up (`App\Platform\Districts`); accounts without one can set it once in settings. After that it is locked: players request a change from settings and an admin approves or declines it in **Admin → Districts**. Approval moves the player and deletes all their game progress (runs, points, statistics, achievements, rank history); the player sees a pop-up about the decision on their next visit (`App\Platform\DistrictChanges`).
- **Standings:** every leaderboard can be viewed country-wide, for the player's region (Northern / Central / Southern), for their district, or for any other district, with positions ranked within that area.

Tests: `php artisan test` (MySQL database `meeplemania_memorymaster_test`) and `npm test` (Vitest).

## How play stays fast: local-first, verified later

Gameplay never waits on the network:

1. Starting a run (`POST /api/play/sessions`) returns the first few **round specs** — never the secret seed. Level Builder and Timed Rush runs are created in the background as the arena opens, so Start is instant.
2. The browser plays rounds locally (`resources/js/game/engine.js`), judges each tap instantly with logic that mirrors the server (`logic.js`), and shows a provisional score (`scoring.js`).
3. Each finished round goes into a **sync queue** (`sync.js`) persisted in `localStorage`. It is sent in the background (`POST …/sync`); each response reveals more rounds ahead. If the connection drops, results stay on the device and sync on reconnect or the next visit.
4. At the end, `POST …/finish` returns the official summary, which replaces the provisional numbers (“Verified by the server”).

The server (`app/Games/PlayService.php`) regenerates every round from the seed, replays the inputs, and computes all points itself. It accepts results only in order, ignores duplicates (so retries are safe), and flags runs whose timing is implausible: taps closer than 70 ms; total claimed play time exceeding the server clock (Level Builder) or the Timed Rush clock; Timed Rush results arriving long after the clock ended. Flagged runs stay off the leaderboards until an admin approves them in **Admin → Reviews**.

The trade-off: the server can bound, but not exactly measure, how fast each answer was, so speed bonuses are capped by plausibility checks rather than timed per round by the server. Rounds are revealed only a few at a time, and a bot reading the screen can't be fully prevented in any browser game — but scores can never be posted directly.

## Architecture

```
app/Games/
  GameDefinition.php       contract: build a round from a seed, evaluate inputs, admin fields
  Definitions/             MemoryMaster, MeeplePairs, PatternPeek, DiceDigits, SpotTheSwap
  GameRegistry.php         installed games (config/games.php) + DB records
  GameSettings.php         config defaults + admin overrides per scope ("platform" or game slug)
  Rng.php                  HMAC-SHA256 deterministic random stream
  PlayService.php          session lifecycle, verification, finalize
  ScoreCalculator.php      all scoring
  StatisticsService.php    per-game stats + platform profile, streaks, personal bests (rebuildable)
  LeaderboardService.php   Board (game|all × category × duration) queries, snapshots, caching
  DailyChallengeService.php
app/Platform/              AchievementService, Presence (swappable store)
resources/js/game/         engine, sync queue, logic, scoring, palette, audio
resources/js/Components/Boards/   one board per game (play, preview, and review modes)
resources/js/Components/Mascot/   MeepleMascot (moods, blinking) and RoamingMeeple (physics, drag & throw)
```

Adding a sixth game: create a `GameDefinition`, add it to `config/games.php`, add a board component, mirror its `evaluate()` in `resources/js/game/logic.js`, and run the seeder.

Leaderboards per game: Highest Level, Timed Rush (30/45/60), Try Once (today), Fastest, Weekly, All-Time; across all games: All-Time, Weekly, Season. Tie-break rules are listed in `app/Games/Board.php` and on the Rules page.

Streaks: the **daily activity streak** counts consecutive days with a ranked game in any game; the **success streak** counts rounds cleared in a row within one run.

Presence: a 45 s heartbeat while the tab is visible, shared across tabs; visitors count as online for 120 s. Implement `App\Platform\Presence\PresenceStore` to move it to Redis.

## Performance notes

Locally on Windows/Herd, Laravel's bootstrap alone is ~200–300 ms per request (even the bare `/up` route); on Linux with php-fpm and OPcache it is typically 20–40 ms. To keep the app feeling instant regardless: gameplay is local-first (above), pages are code-split, and navigation links prefetch on hover via Inertia.
