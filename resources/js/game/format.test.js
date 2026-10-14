import { describe, expect, it } from 'vitest';
import { compact, points } from './format';

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
});
