import {
    Anchor, Bell, Clover, Crown, Dices, Fish, Flame, Gem, Ghost, Heart, Hourglass, Key, Leaf, Moon, Music,
    Puzzle, Rocket, ShieldCheck, Star, Sun, Zap,
    Award, Compass, Eye, Gamepad2, Layers, LayoutGrid, Medal, Sparkles, TrendingUp, Trophy,
    Cat, Bird, Snowflake,
} from 'lucide-vue-next';

/** Colour families: base, soft background, deep (text / outline). */
export const COLORS = {
    amber: { base: '#f59e0b', soft: '#fff1d6', deep: '#9a5b00' },
    ruby: { base: '#ef4444', soft: '#fee2e2', deep: '#b91c1c' },
    jade: { base: '#10b981', soft: '#d6f7ea', deep: '#047857' },
    sapphire: { base: '#2f6fe4', soft: '#dde9fd', deep: '#1d4ed8' },
    amethyst: { base: '#8b5cf6', soft: '#ede9fe', deep: '#6d28d9' },
    coral: { base: '#ec4899', soft: '#fce7f3', deep: '#be185d' },
    lime: { base: '#65a30d', soft: '#ecfccb', deep: '#3f6212' },
    teal: { base: '#0d9488', soft: '#ccfbf1', deep: '#0f766e' },
    slate: { base: '#64748b', soft: '#e2e8f0', deep: '#334155' },
};

/** Lucide components referenced by name from the server (games, achievements). */
export const ICONS = {
    Crown, Dices, ShieldCheck, Star, Gem, Heart, Clover, Anchor, Hourglass, Rocket, Ghost,
    Trophy, Layers, LayoutGrid, Eye, Zap, Sparkles, Flame, Medal, Award, TrendingUp, Gamepad2, Compass,
};

export function colorVars(colorKey) {
    const c = COLORS[colorKey] ?? COLORS.amber;
    return { '--tile': c.base, '--tile-soft': c.soft, '--tile-deep': c.deep };
}

/** Memory Master tiles; a board with N tiles uses the first N. */
export const TILES = [
    { key: 'crown', name: 'Crown Meeple', color: 'amber', colorName: 'Amber', icon: Crown, frequency: 523.25 },
    { key: 'dice', name: 'Ruby Dice', color: 'ruby', colorName: 'Crimson', icon: Dices, frequency: 659.25 },
    { key: 'shield', name: 'Jade Shield', color: 'jade', colorName: 'Emerald', icon: ShieldCheck, frequency: 783.99 },
    { key: 'star', name: 'Sapphire Star', color: 'sapphire', colorName: 'Cobalt', icon: Star, frequency: 1046.5 },
    { key: 'gem', name: 'Amethyst Gem', color: 'amethyst', colorName: 'Violet', icon: Gem, frequency: 587.33 },
    { key: 'heart', name: 'Coral Heart', color: 'coral', colorName: 'Coral', icon: Heart, frequency: 880 },
    { key: 'clover', name: 'Lucky Clover', color: 'lime', colorName: 'Lime', icon: Clover, frequency: 698.46 },
    { key: 'anchor', name: 'Teal Anchor', color: 'teal', colorName: 'Teal', icon: Anchor, frequency: 987.77 },
    { key: 'hourglass', name: 'Slate Hourglass', color: 'slate', colorName: 'Slate', icon: Hourglass, frequency: 440 },
];

/** Meeple Pairs card faces (index = symbol id from the server; 18 required). */
const PAIR_COLORS = ['amber', 'ruby', 'jade', 'sapphire', 'amethyst', 'coral', 'lime', 'teal', 'slate'];
export const PAIR_SYMBOLS = [
    Crown, Dices, ShieldCheck, Star, Gem, Heart, Clover, Anchor, Hourglass,
    Rocket, Ghost, Flame, Moon, Sun, Music, Puzzle, Key, Bell,
].map((icon, i) => ({ icon, color: PAIR_COLORS[i % PAIR_COLORS.length], name: icon.name ?? `Symbol ${i + 1}` }));

/** Spot the Swap token shapes (8) and colours (6). */
export const SWAP_ICONS = [Star, Heart, Crown, Leaf, Fish, Bird, Cat, Snowflake];
export const SWAP_ICON_NAMES = ['star', 'heart', 'crown', 'leaf', 'fish', 'bird', 'cat', 'snowflake'];
export const SWAP_COLORS = ['ruby', 'amber', 'jade', 'sapphire', 'amethyst', 'slate'];

/** Tones for the Dice Digits keypad (1–6). */
export const DIE_TONES = [392, 440, 523.25, 587.33, 659.25, 783.99];

/** Preset avatars a player can choose (keys mirror User::AVATARS). */
export const AVATARS = {
    meeple: { label: 'Coach Meeple', image: '/images/meeple-figure.png', color: 'amber' },
    crown: { label: 'Crown', icon: Crown, color: 'amber' },
    dice: { label: 'Dice', icon: Dices, color: 'ruby' },
    shield: { label: 'Shield', icon: ShieldCheck, color: 'jade' },
    star: { label: 'Star', icon: Star, color: 'sapphire' },
    gem: { label: 'Gem', icon: Gem, color: 'amethyst' },
    heart: { label: 'Heart', icon: Heart, color: 'coral' },
    clover: { label: 'Clover', icon: Clover, color: 'lime' },
    rocket: { label: 'Rocket', icon: Rocket, color: 'teal' },
    ghost: { label: 'Ghost', icon: Ghost, color: 'slate' },
};

