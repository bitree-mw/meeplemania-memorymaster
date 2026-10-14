import { describe, expect, it } from 'vitest';
import { compact, points, rankPoints } from './format';
import { describeRow } from './leaderboard';

describe('compact numbers', () => {
    it('keeps the short form for counts', () => {
        expect(compact(9_999)).toBe(new Intl.NumberFormat().format(9_999));
        expect(compact(12_345)).toBe('12k');
        expect(compact(1_250_000)).toBe('1.3m');
    });

    it('shows points to three decimals from 10k, never rounding up', () => {
        expect(points(9_999)).toBe(new Intl.NumberFormat().format(9_999));
        expect(points(10_000)).toBe('10.000k');
        expect(points(12_345)).toBe('12.345k');
        expect(points(999_999)).toBe('999.999k');
        expect(points(1_234_567)).toBe('1.234m');
        expect(points(1_999_999)).toBe('1.999m');
    });

    it('shows ranking points to two decimals from 10k', () => {
        expect(rankPoints(12_345)).toBe('12.34k');
        expect(rankPoints(10_000)).toBe('10.00k');
        expect(rankPoints(2_345_678)).toBe('2.34m');
        expect(describeRow('all_time', { points: 12_345, games: 3 }).primary).toBe('12.34k pts');
        expect(describeRow('timed', { score: 15_999 }).primary).toBe('15.99k pts');
        expect(describeRow('weekly', { points: 950, games: 1 }).primary).toBe(`${new Intl.NumberFormat().format(950)} pts`);
    });
});
