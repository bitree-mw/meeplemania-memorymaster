import { defineAsyncComponent } from 'vue';
import MemoryBoard from '@/Components/Boards/MemoryBoard.vue';

/** Board component per game. Memory Master ships in the arena bundle; the others load on demand. */
export const BOARDS = {
    'memory-master': MemoryBoard,
    'meeple-pairs': defineAsyncComponent(() => import('@/Components/Boards/PairsBoard.vue')),
    'pattern-peek': defineAsyncComponent(() => import('@/Components/Boards/PatternBoard.vue')),
    'dice-digits': defineAsyncComponent(() => import('@/Components/Boards/DiceBoard.vue')),
    'spot-the-swap': defineAsyncComponent(() => import('@/Components/Boards/SwapBoard.vue')),
};
