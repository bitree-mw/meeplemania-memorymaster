# Meeple Mania Games

Laravel 13 + Inertia 3 + Vue 3 (JavaScript, Composition API) + Tailwind 4 + MySQL. Five memory games × three modes (Level Builder, Timed Rush 30/45/60s, Try Once). See README.md for rules, architecture and the local-first verification protocol.

## Commands

- PHP via Herd: `php` may not be on PATH in bash; use `~/.config/herd/bin/php84/php.exe`.
- `php artisan test` — against MySQL `meeplemania_memorymaster_test`. `npm test` — Vitest. `npm run build`.
- `vendor/bin/pint` — PHP code style.

## Conventions

- Rules live in `config/games.php`, read through `App\Games\GameSettings`; sessions snapshot rules at start.
- A game = `App\Games\GameDefinition` subclass + board component + entry in `resources/js/game/logic.js` that mirrors `evaluate()` exactly.
- All official scoring happens server-side (`ScoreCalculator`); `resources/js/game/scoring.js` is a provisional mirror.
- Keep `resources/js/game/engine.js` framework-agnostic and covered by `engine.test.js`.
- Do not seed fake players or scores. Do not modify `uibuilder/`.
