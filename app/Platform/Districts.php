<?php

namespace App\Platform;

use Illuminate\Support\Str;

/**
 * The 28 districts of Malawi and their regions. A player picks a district when
 * signing up (changes go through an admin, see DistrictChanges); it powers the
 * district and region leaderboards. Stored on users.district as the slug key.
 */
final class Districts
{
    public const REGIONS = [
        'Northern' => ['Chitipa', 'Karonga', 'Likoma', 'Mzimba', 'Nkhata Bay', 'Rumphi'],
        'Central' => ['Dedza', 'Dowa', 'Kasungu', 'Lilongwe', 'Mchinji', 'Nkhotakota', 'Ntcheu', 'Ntchisi', 'Salima'],
        'Southern' => ['Balaka', 'Blantyre', 'Chikwawa', 'Chiradzulu', 'Machinga', 'Mangochi', 'Mulanje', 'Mwanza', 'Neno', 'Nsanje', 'Phalombe', 'Thyolo', 'Zomba'],
    ];

    /** @return array<string, string> slug => name, alphabetical */
    public static function all(): array
    {
        return collect(self::REGIONS)->flatten()->sort()->mapWithKeys(fn (string $name) => [Str::slug($name) => $name])->all();
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function name(?string $key): ?string
    {
        return $key === null ? null : (self::all()[$key] ?? null);
    }

    /** @return array<string, string> region slug => "Northern Region" */
    public static function regions(): array
    {
        return collect(array_keys(self::REGIONS))->mapWithKeys(fn (string $r) => [Str::slug($r) => "{$r} Region"])->all();
    }

    public static function regionOf(?string $district): ?string
    {
        foreach (self::REGIONS as $region => $names) {
            if (in_array(self::name($district), $names, true)) {
                return Str::slug($region);
            }
        }

        return null;
    }

    /** @return list<string> district slugs in a region */
    public static function inRegion(string $region): array
    {
        $names = collect(self::REGIONS)->first(fn ($names, string $r) => Str::slug($r) === $region) ?? [];

        return array_map(fn (string $name) => Str::slug($name), $names);
    }

    /** Alphabetical options for <select> menus: [{key, name, region}]. */
    public static function options(): array
    {
        return collect(self::all())->map(fn (string $name, string $key) => ['key' => $key, 'name' => $name, 'region' => self::regionOf($key)])->values()->all();
    }

    /** [{key, name}] for the three regions. */
    public static function regionOptions(): array
    {
        return collect(self::regions())->map(fn (string $name, string $key) => ['key' => $key, 'name' => $name])->values()->all();
    }
}
