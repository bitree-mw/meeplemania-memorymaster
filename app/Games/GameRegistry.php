<?php

namespace App\Games;

use App\Models\Game;
use App\Models\GameMode;
use Illuminate\Support\Collection;

/**
 * The installed games (config/games.php, in display order) and their
 * database records. Registered as a singleton.
 */
class GameRegistry
{
    /** @var array<string, GameDefinition> */
    private array $definitions = [];

    /** @var Collection<string, Game>|null */
    private ?Collection $games = null;

    /** @var Collection<int, GameMode>|null */
    private ?Collection $modes = null;

    public function __construct()
    {
        foreach (config('games.games') as $slug => $entry) {
            $this->definitions[$slug] = app($entry['class']);
        }
    }

    /** @return array<string, GameDefinition> */
    public function definitions(): array
    {
        return $this->definitions;
    }

    public function slugs(): array
    {
        return array_keys($this->definitions);
    }

    public function has(string $slug): bool
    {
        return isset($this->definitions[$slug]);
    }

    public function definition(string $slug): GameDefinition
    {
        return $this->definitions[$slug] ?? throw new \InvalidArgumentException("Unknown game [{$slug}].");
    }

    /** @return Collection<string, Game> */
    public function games(): Collection
    {
        return $this->games ??= Game::query()->orderBy('sort_order')->get()->keyBy('slug');
    }

    public function game(string $slug): ?Game
    {
        return $this->games()->get($slug);
    }

    public function gameById(int $id): ?Game
    {
        return $this->games()->firstWhere('id', $id);
    }

    public function mode(Game $game, string $mode): ?GameMode
    {
        $this->modes ??= GameMode::all();

        return $this->modes->first(fn (GameMode $m) => $m->game_id === $game->id && $m->slug === $mode);
    }

    public function definitionFor(Game $game): GameDefinition
    {
        return $this->definition($game->slug);
    }

    /** Active games with their definition metadata, in display order. */
    public function catalogue(): array
    {
        return $this->games()
            ->filter(fn (Game $g) => $g->is_active && $this->has($g->slug))
            ->map(fn (Game $g) => ['id' => $g->id, ...$this->definition($g->slug)->toArray()])
            ->values()
            ->all();
    }
}
