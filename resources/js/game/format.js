const number = new Intl.NumberFormat();

export const fmt = (n) => number.format(Number(n ?? 0));

export function compact(n) {
    n = Number(n ?? 0);
    if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1).replace(/\.0$/, '')}m`;
    if (n >= 10_000) return `${Math.round(n / 1000)}k`;
    return fmt(n);
}

export function seconds(ms, digits = 2) {
    if (ms === null || ms === undefined) return '—';
    return `${(ms / 1000).toFixed(digits)}s`;
}

export function duration(totalSeconds) {
    if (totalSeconds === null || totalSeconds === undefined) return '—';
    const m = Math.floor(totalSeconds / 60);
    const s = Math.round(totalSeconds % 60);
    return m ? `${m}m ${String(s).padStart(2, '0')}s` : `${s}s`;
}

export function ordinal(n) {
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
}

export function relativeTime(iso) {
    if (!iso) return '';
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    if (diff < 60) return 'just now';
    if (diff < 3600) return rtf.format(-Math.round(diff / 60), 'minute');
    if (diff < 86400) return rtf.format(-Math.round(diff / 3600), 'hour');
    return rtf.format(-Math.round(diff / 86400), 'day');
}

export function shortDate(iso) {
    return new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}

export const END_REASONS = {
    wrong_tile: 'Wrong tile',
    timeout: 'Ran out of time',
    abandoned: 'Round left unfinished',
    integrity: 'Could not be verified',
    exited: 'Left the arena',
    replaced: 'Started a new game',
    idle: 'Inactive',
    time_up: "Time's up",
    challenge_complete: 'Cup complete',
};
