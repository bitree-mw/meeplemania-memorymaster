import { rankPoints, seconds } from './format';

const plural = (n, word) => `${n} ${word}${Number(n) === 1 ? '' : 's'}`;
const accuracy = (r) => (r.expected_inputs ? `${Math.round((r.correct_inputs / r.expected_inputs) * 100)}% acc` : null);

/** Primary and supporting values for a leaderboard row in each board category. */
export function describeRow(board, r, unit = 'item') {
    switch (board) {
        case 'level':
            return { primary: `Lvl ${r.level}`, secondary: [r.avg_unit_ms ? `${seconds(r.avg_unit_ms)}/${unit}` : null, accuracy(r)].filter(Boolean).join(' · ') };
        case 'fastest':
            return { primary: `${seconds(r.avg_unit_ms)}/${unit}`, secondary: `Lvl ${r.level}` };
        case 'timed':
            return { primary: `${rankPoints(r.score)} pts`, secondary: 'Best run' };
        case 'once':
            return { primary: `${rankPoints(r.score)} pts`, secondary: `Lvl ${r.level} · ${Math.round(r.accuracy)}% acc` };
        case 'weekly':
        case 'season':
            return { primary: `${rankPoints(r.points)} pts`, secondary: plural(r.games, 'game') };
        case 'all_time':
            return {
                primary: `${rankPoints(r.points)} pts`,
                secondary: r.level !== undefined ? `Best Lvl ${r.level} · ${plural(r.games, 'game')}` : `${plural(r.games, 'game')}${r.streak ? ` · ${r.streak}-day streak` : ''}`,
            };
        default:
            return { primary: '', secondary: '' };
    }
}
