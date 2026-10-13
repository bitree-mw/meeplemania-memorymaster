<?php

namespace App\Games;

use App\Models\GameSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Centralised, admin-configurable rules.
 *
 * Defaults come from config/games.php. Overrides are stored per (scope, section)
 * in `game_settings`, where scope is "platform" or a game slug, and replace
 * individual keys of that section.
 */
class GameSettings
{
    public const CACHE_KEY = 'games.settings';

    public const PLATFORM = 'platform';

    public const PLATFORM_SECTIONS = ['daily', 'fair_play', 'streaks', 'presence'];

    public const GAME_SECTIONS = ['classic', 'timed', 'once', 'scoring', 'game'];

    /**
     * @return array{platform: array, games: array<string, array>}
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $all = [
                'platform' => config('games.rules'),
                'games' => array_map(fn ($entry) => $entry['rules'], config('games.games')),
            ];

            foreach (GameSetting::all() as $override) {
                $target = $override->scope === self::PLATFORM ? "platform.{$override->key}" : "games.{$override->scope}.{$override->key}";
                $current = Arr::get($all, $target);

                if (is_array($current)) {
                    Arr::set($all, $target, array_merge($current, (array) $override->value));
                }
            }

            return $all;
        });
    }

    public function platform(?string $section = null): array
    {
        $platform = $this->all()['platform'];

        return $section ? $platform[$section] : $platform;
    }

    /** All rule sections for one game. */
    public function game(string $slug): array
    {
        return $this->all()['games'][$slug] ?? throw new \InvalidArgumentException("Unknown game [{$slug}].");
    }

    public function get(string $path, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $path, $default);
    }

    public function update(string $scope, string $section, array $values, ?User $by = null): void
    {
        GameSetting::updateOrCreate(
            ['scope' => $scope, 'key' => $section],
            ['value' => $values, 'updated_by' => $by?->id],
        );

        $this->flush();
    }

    public function reset(string $scope, string $section): void
    {
        GameSetting::where('scope', $scope)->where('key', $section)->delete();

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function timezone(): string
    {
        return $this->platform('daily')['timezone'] ?? 'UTC';
    }

    /** "Now" in the game timezone, for calendar-day logic (streaks, Try Once). */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    public function today(): string
    {
        return $this->now()->toDateString();
    }
}
