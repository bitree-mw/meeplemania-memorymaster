<script setup>
/**
 * Illustrated home banner: a midnight game table with the Meeple Mania badge
 * at its centre and live pieces from all five games around it. Pure CSS/SVG,
 * sized in container units so it stays crisp at any width; animations stop
 * under prefers-reduced-motion.
 */
import Die from '@/Components/Boards/Die.vue';
import { COLORS, TILES } from '@/game/palette';
import { Bird, Heart, Leaf, Star } from 'lucide-vue-next';

const tiles = TILES.slice(0, 4);
const lit = [0, 4, 5, 7];
</script>

<template>
    <div class="scene" role="img" aria-label="A game table with pieces from the five MeepleMania games around the MeepleMania badge">
        <!-- Table felt -->
        <div class="felt" aria-hidden="true" />

        <!-- Memory Master: tiles flashing in sequence -->
        <div class="piece memory" aria-hidden="true">
            <span v-for="(t, i) in tiles" :key="t.key" class="mtile" :style="{ '--c': COLORS[t.color].base, '--soft': COLORS[t.color].soft, animationDelay: `${i * 0.6}s` }">
                <component :is="t.icon" class="micon" :stroke-width="2.6" />
            </span>
        </div>

        <!-- Meeple Pairs: one card flipping -->
        <div class="piece pairs" aria-hidden="true">
            <span class="card face"><Heart class="cicon" :style="{ color: COLORS.coral.base }" fill="currentColor" fill-opacity="0.2" :stroke-width="2.4" /></span>
            <span class="card flipper">
                <span class="card back inner"><img src="/images/meeple-figure.png" alt="" class="h-[44%] w-auto opacity-30 brightness-0 invert" /></span>
                <span class="card face inner front"><Heart class="cicon" :style="{ color: COLORS.coral.base }" fill="currentColor" fill-opacity="0.2" :stroke-width="2.4" /></span>
            </span>
        </div>

        <!-- Pattern Peek: a blinking grid -->
        <div class="piece pattern" aria-hidden="true">
            <span v-for="i in 9" :key="i" class="cell" :class="{ on: lit.includes(i - 1) }" :style="{ animationDelay: `${(i % 4) * 0.35}s` }" />
        </div>

        <!-- Dice Digits: tumbling dice -->
        <div class="piece dice" aria-hidden="true">
            <span class="die d1"><Die :value="5" :size="100" /></span>
            <span class="die d2"><Die :value="3" tone="ruby" :size="100" /></span>
            <span class="die d3"><Die :value="6" :size="100" /></span>
        </div>

        <!-- Spot the Swap: one token keeps changing colour -->
        <div class="piece swap" aria-hidden="true">
            <span class="token" :style="{ '--c': COLORS.amber.base, '--soft': COLORS.amber.soft }"><Star class="ticon" fill="currentColor" fill-opacity="0.25" /></span>
            <span class="token swapper"><Leaf class="ticon" fill="currentColor" fill-opacity="0.25" /></span>
            <span class="token" :style="{ '--c': COLORS.sapphire.base, '--soft': COLORS.sapphire.soft }"><Bird class="ticon" fill="currentColor" fill-opacity="0.25" /></span>
        </div>

        <!-- The badge -->
        <div class="badge">
            <span class="halo" aria-hidden="true" />
            <!-- Variant of the logo with white lettering, readable on the dark table. -->
            <img src="/images/meeplemania-logo-light.png" alt="" class="logo" width="512" height="483" />
        </div>

        <span v-for="n in 6" :key="n" class="spark" :class="`s${n}`" aria-hidden="true">✦</span>
    </div>
</template>

<style scoped>
.scene {
    container-type: inline-size;
    position: relative;
    width: 100%;
    /* Never set the width of the grid column it sits in (Safari sizes it from content). */
    min-width: 0;
    max-width: 100%;
    aspect-ratio: 16 / 10.5;
    overflow: hidden;
    border-radius: 1.75rem;
    background: var(--color-navy-deep);
    box-shadow: 0 0 0 4px #fff, var(--shadow-soft);
}
.felt {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse at 50% 48%, rgb(254 166 25 / 0.28), transparent 46%),
        radial-gradient(circle at 1px 1px, rgb(255 255 255 / 0.07) 1px, transparent 1.6px) 0 0 / 3.2cqw 3.2cqw,
        radial-gradient(ellipse at 50% 120%, #22385a, transparent 70%),
        linear-gradient(160deg, #18304d, var(--color-navy-deep) 70%);
}
.piece {
    position: absolute;
    animation: float 5s ease-in-out infinite;
}

/* Memory Master */
.memory {
    left: 5%;
    top: 9%;
    display: grid;
    grid-template-columns: repeat(2, 9cqw);
    gap: 1.4cqw;
    transform: rotate(-8deg);
}
.mtile {
    display: grid;
    place-items: center;
    aspect-ratio: 1;
    border-radius: 2.4cqw;
    background: #fff;
    box-shadow: 0 0.8cqw 0 #c9c3b6;
    animation: flash 2.4s infinite;
}
.micon { width: 50%; height: 50%; color: var(--c); }
@keyframes flash {
    0%, 22%, 100% { background: #fff; transform: scale(1); box-shadow: 0 0.8cqw 0 #c9c3b6; }
    8% { background: var(--soft); transform: scale(1.12); box-shadow: 0 0 0 0.6cqw var(--c), 0 0 3cqw 0.6cqw var(--c); }
}

/* Meeple Pairs */
.pairs {
    right: 6%;
    top: 7%;
    display: flex;
    gap: 1.6cqw;
    transform: rotate(9deg);
    animation-delay: -1.5s;
}
.card {
    position: relative;
    display: grid;
    place-items: center;
    width: 10cqw;
    height: 13.5cqw;
    border-radius: 1.8cqw;
    border: 0.5cqw solid #fff;
    background: var(--color-ruby-soft);
    box-shadow: 0 1cqw 1.6cqw -0.6cqw rgb(0 0 0 / 0.5);
}
.cicon { width: 55%; height: 55%; }
.flipper {
    border: 0;
    background: none;
    box-shadow: none;
    perspective: 60cqw;
}
.inner {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    backface-visibility: hidden;
    animation: flip 4.5s ease-in-out infinite;
}
.back { background: linear-gradient(145deg, #1b2c42, #0d1b2a); }
.front { animation-name: flip-front; }
@keyframes flip { 0%, 40% { transform: rotateY(0); } 55%, 90% { transform: rotateY(180deg); } 100% { transform: rotateY(360deg); } }
@keyframes flip-front { 0%, 40% { transform: rotateY(-180deg); } 55%, 90% { transform: rotateY(0); } 100% { transform: rotateY(180deg); } }

/* Pattern Peek */
.pattern {
    left: 7%;
    bottom: 9%;
    display: grid;
    grid-template-columns: repeat(3, 4.6cqw);
    gap: 0.9cqw;
    padding: 1.2cqw;
    border-radius: 2cqw;
    background: rgb(255 255 255 / 0.08);
    transform: rotate(6deg);
    animation-delay: -3s;
}
.cell {
    aspect-ratio: 1;
    border-radius: 1cqw;
    background: rgb(255 255 255 / 0.12);
}
.cell.on { animation: glow 2.8s ease-in-out infinite; }
@keyframes glow {
    0%, 60%, 100% { background: rgb(255 255 255 / 0.12); box-shadow: none; }
    15%, 45% { background: var(--color-amber); box-shadow: 0 0 1.6cqw 0.2cqw rgb(245 158 11 / 0.7); }
}

/* Dice Digits */
.dice {
    right: 7%;
    bottom: 8%;
    width: 24cqw;
    height: 15cqw;
    animation: none;
}
.die {
    position: absolute;
    width: 8.5cqw;
    height: 8.5cqw;
    filter: drop-shadow(0 0.8cqw 0.6cqw rgb(0 0 0 / 0.45));
}
.die :deep(svg) { width: 100%; height: 100%; }
.d1 { left: 0; bottom: 0; animation: roll 3.6s ease-in-out infinite; }
.d2 { left: 8cqw; top: 0; animation: roll 3.6s ease-in-out -1.2s infinite reverse; }
.d3 { right: 0; bottom: 1cqw; animation: roll 3.6s ease-in-out -2.4s infinite; }
@keyframes roll {
    0%, 100% { transform: rotate(-14deg) translateY(0); }
    50% { transform: rotate(18deg) translateY(-1.6cqw); }
}

/* Spot the Swap */
.swap {
    left: 50%;
    bottom: 4%;
    display: flex;
    gap: 1.2cqw;
    transform: translateX(-50%);
    animation-name: float-x;
    animation-delay: -2s;
}
.token {
    display: grid;
    place-items: center;
    width: 7cqw;
    height: 7cqw;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 0.7cqw 0 #c9c3b6;
}
.ticon {
    width: 60%;
    height: 60%;
    padding: 12%;
    border-radius: 50%;
    background: var(--soft);
    color: var(--c);
    box-sizing: content-box;
}
.swapper { animation: swap 3.2s steps(1) infinite; }
@keyframes swap {
    0% { --c: #10b981; --soft: #d6f7ea; }
    50% { --c: #8b5cf6; --soft: #ede9fe; }
}

/* Badge */
.badge {
    position: absolute;
    left: 50%;
    top: 46%;
    width: 36cqw;
    transform: translate(-50%, -50%);
    animation: badge 6s ease-in-out infinite;
}
.halo {
    position: absolute;
    inset: 6%;
    border-radius: 50%;
    background: radial-gradient(circle, rgb(255 221 184 / 0.55), transparent 68%);
    filter: blur(1cqw);
}
.logo {
    position: relative;
    width: 100%;
    height: auto;
    filter: drop-shadow(0 1.2cqw 1.6cqw rgb(0 0 0 / 0.45));
}
@keyframes badge {
    0%, 100% { transform: translate(-50%, -50%) rotate(-2deg) scale(1); }
    50% { transform: translate(-50%, -53%) rotate(2deg) scale(1.03); }
}

/* Sparkles */
.spark {
    position: absolute;
    color: var(--color-amber-dim);
    font-size: 2.4cqw;
    animation: twinkle 2.6s ease-in-out infinite;
}
.s1 { left: 30%; top: 12%; }
.s2 { right: 30%; top: 20%; font-size: 1.6cqw; animation-delay: -0.7s; }
.s3 { left: 33%; bottom: 26%; font-size: 1.8cqw; animation-delay: -1.3s; }
.s4 { right: 33%; bottom: 30%; animation-delay: -1.9s; }
.s5 { left: 46%; top: 6%; font-size: 1.4cqw; animation-delay: -0.4s; }
.s6 { right: 14%; top: 50%; font-size: 1.6cqw; animation-delay: -2.2s; color: #fff; }
@keyframes twinkle {
    0%, 100% { opacity: 0.2; transform: scale(0.7) rotate(0deg); }
    50% { opacity: 1; transform: scale(1.15) rotate(25deg); }
}

@keyframes float {
    0%, 100% { translate: 0 0; }
    50% { translate: 0 -1.4cqw; }
}
@keyframes float-x {
    0%, 100% { translate: 0 0; }
    50% { translate: 0 -1cqw; }
}
</style>
