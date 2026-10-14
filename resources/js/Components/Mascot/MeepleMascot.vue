<script setup>
/**
 * Coach Meeple, animated from the original artwork.
 *
 * meeple-figure.png is drawn six times, each copy clipped to one body part
 * (head, torso, two arms, two legs) pivoting at its neck, shoulder or hip.
 * That allows overlapping action: the head lags and follows through behind
 * the body, limbs swing on their own timing, and the body squashes and
 * stretches. Eyelids blink on their own. Under prefers-reduced-motion the
 * global CSS rule stills everything.
 */
defineProps({
    // idle | walk | run | watch | cheer | sad | think | wave | air | dangle | stumble | fallen | getup | peek
    mood: { type: String, default: 'idle' },
    size: { type: Number, default: 64 },
    shadow: { type: Boolean, default: true },
});

const SRC = '/images/meeple-figure.png';
</script>

<template>
    <span class="mascot" :class="`mood-${mood}`" :style="{ width: `${size}px`, height: `${size * 1.255}px`, fontSize: `${size}px` }" aria-hidden="true">
        <span v-if="shadow" class="shadow" />
        <span :key="mood" class="body">
            <img :src="SRC" alt="" draggable="false" class="part leg-left" />
            <img :src="SRC" alt="" draggable="false" class="part leg-right" />
            <img :src="SRC" alt="" draggable="false" class="part arm-left" />
            <img :src="SRC" alt="" draggable="false" class="part arm-right" />
            <img :src="SRC" alt="" draggable="false" class="part torso" />
            <span class="head">
                <img :src="SRC" alt="" draggable="false" class="part head-img" />
                <span class="lid left" />
                <span class="lid right" />
                <span class="dizzy"><i>✦</i><i>✦</i><i>✦</i></span>
            </span>
        </span>
    </span>
</template>

<style scoped>
.mascot {
    position: relative;
    display: inline-block;
    flex-shrink: 0;
}
.body {
    position: absolute;
    inset: 0;
    transform-origin: 50% 100%;
    filter: drop-shadow(0 3px 4px rgb(13 27 42 / 0.18));
}
.shadow {
    position: absolute;
    left: 18%;
    right: 18%;
    bottom: -4%;
    height: 9%;
    border-radius: 50%;
    background: rgb(13 27 42 / 0.16);
    filter: blur(1px);
    transition: transform 0.4s ease, opacity 0.3s ease;
}

/* Body parts: the same image clipped, each with its own pivot (measured from the PNG). */
.part {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    user-select: none;
    -webkit-user-drag: none;
}
.head { position: absolute; inset: 0; transform-origin: 50% 40%; }
.head-img { clip-path: polygon(20.75% 0, 79.25% 0, 79.25% 41%, 20.75% 41%); }
/* The torso stops at the body outline beside the arms (30%→25.5% left, 69%→73.5% right), so no
   stub of arm stays behind when an arm rotates. Each arm is everything outside that line plus a
   round shoulder joint centred on its pivot, sized to the arm's thickness where it meets the body
   (ry 8.4%/8.6% of the box height; rx = ry × 266/212 keeps it circular). A circle is unchanged by
   rotation, so bending never opens a gap; the torso on top hides the inner half. */
.torso { clip-path: polygon(30% 37.5%, 69% 37.5%, 73.5% 58%, 79.25% 58%, 79.25% 75.2%, 20.75% 75.2%, 20.75% 58%, 25.5% 58%); }
.arm-left {
    clip-path: polygon(0 38%, 30% 38%, 29.54% 40.03%, 31.68% 40.37%, 33.69% 41.05%, 35.48% 42.04%, 36.98% 43.3%, 38.12% 44.79%, 38.84% 46.43%, 39.13% 48.15%, 38.97% 49.88%, 38.37% 51.55%, 37.34% 53.09%, 35.95% 54.42%, 34.23% 55.5%, 32.28% 56.27%, 30.17% 56.71%, 27.99% 56.79%, 25.84% 56.51%, 25.5% 58%, 0 58%);
    transform-origin: 28.6% 48.4%;
}
.arm-right {
    clip-path: polygon(100% 38%, 69% 38%, 69.51% 40.28%, 67.41% 40.69%, 65.45% 41.43%, 63.71% 42.46%, 62.27% 43.75%, 61.18% 45.24%, 60.48% 46.88%, 60.21% 48.6%, 60.38% 50.32%, 60.97% 51.98%, 61.97% 53.51%, 63.34% 54.86%, 65.01% 55.95%, 66.93% 56.76%, 69.01% 57.25%, 71.17% 57.4%, 73.32% 57.2%, 73.5% 58%, 100% 58%);
    transform-origin: 71% 48.8%;
}
.leg-left { clip-path: polygon(0 69.5%, 49.1% 69.5%, 49.1% 100%, 0 100%); transform-origin: 34% 71.5%; }
.leg-right { clip-path: polygon(49.1% 69.5%, 100% 69.5%, 100% 100%, 49.1% 100%); transform-origin: 64% 71.5%; }

/* Eyelids over the painted eyes (they move with the head). */
.lid {
    position: absolute;
    top: 12.6%;
    width: 13%;
    height: 12.4%;
    border-radius: 50%;
    background: #f7f7f7;
    transform: scaleY(0);
    transform-origin: 50% 0;
    animation: blink 4.6s infinite;
}
.lid.left { left: 30.4%; }
.lid.right { left: 50.3%; }

/* Dizzy stars, shown only after a fall. */
.dizzy { display: none; position: absolute; left: 50%; top: -6%; width: 0; height: 0; }
.dizzy i {
    position: absolute;
    left: -0.35em;
    top: -0.5em;
    font-style: normal;
    font-size: max(10px, 0.18em);
    color: var(--color-amber);
    animation: orbit 1.1s linear infinite;
}
.dizzy i:nth-child(2) { animation-delay: -0.37s; color: var(--color-sky); }
.dizzy i:nth-child(3) { animation-delay: -0.74s; color: var(--color-ruby); }

@keyframes blink {
    0%, 93%, 100% { transform: scaleY(0); }
    95.5% { transform: scaleY(1); }
}
@keyframes orbit {
    from { transform: rotate(0deg) translateX(1.1em) rotate(0deg) scale(1); }
    50% { transform: rotate(180deg) translateX(1.1em) rotate(-180deg) scale(0.7); }
    to { transform: rotate(360deg) translateX(1.1em) rotate(-360deg) scale(1); }
}

/* ===================== Moods ===================== */

/* Idle: breathing, head drifting a beat behind, arms swaying. */
.mood-idle .body { animation: breathe 3.2s ease-in-out infinite; }
.mood-idle .head { animation: head-drift 3.2s ease-in-out -0.5s infinite; }
.mood-idle .arm-left { animation: arm-rest-l 3.2s ease-in-out infinite; }
.mood-idle .arm-right { animation: arm-rest-r 3.2s ease-in-out -0.3s infinite; }

/* Walk: alternating legs, opposite arms, bob with squash on each footfall, head lagging.
   Negative delays start mid-stride so the cycle never snaps from a neutral pose. */
.mood-walk .body { animation: walk-bob 0.5s ease-in-out infinite; }
.mood-walk .head { animation: head-bob 0.5s ease-in-out -0.08s infinite; }
.mood-walk .leg-left { animation: stride 0.5s ease-in-out -0.125s infinite; }
.mood-walk .leg-right { animation: stride 0.5s ease-in-out -0.375s infinite; }
.mood-walk .arm-left { animation: swing 0.5s ease-in-out -0.375s infinite; }
.mood-walk .arm-right { animation: swing 0.5s ease-in-out -0.125s infinite; }

/* Run: long quick strides, leaning in. */
.mood-run .body { animation: run-bob 0.3s ease-in-out infinite; }
.mood-run .head { animation: head-bob 0.3s ease-in-out -0.05s infinite; }
.mood-run .leg-left { animation: stride-long 0.3s ease-in-out -0.075s infinite; }
.mood-run .leg-right { animation: stride-long 0.3s ease-in-out -0.225s infinite; }
.mood-run .arm-left { animation: swing-long 0.3s ease-in-out -0.225s infinite; }
.mood-run .arm-right { animation: swing-long 0.3s ease-in-out -0.075s infinite; }

/* Wave: right arm waving, head tilted toward it. */
.mood-wave .body { animation: breathe 2s ease-in-out infinite; }
.mood-wave .head { animation: wave-head 0.9s ease-in-out infinite; }
.mood-wave .arm-right { animation: wave-arm 0.45s ease-in-out infinite alternate; }

/* Cheer: hop with both arms up, legs tucking. */
.mood-cheer .body { animation: cheer 0.9s cubic-bezier(0.3, 0, 0.3, 1) infinite; }
.mood-cheer .head { animation: cheer-head 0.9s ease-in-out -0.1s infinite; }
.mood-cheer .shadow { animation: cheer-shadow 0.9s cubic-bezier(0.3, 0, 0.3, 1) infinite; }
.mood-cheer .arm-left { animation: arms-up-l 0.45s ease-in-out infinite alternate; }
.mood-cheer .arm-right { animation: arms-up-r 0.45s ease-in-out -0.1s infinite alternate; }
.mood-cheer .leg-left { animation: kick-l 0.9s ease-in-out infinite; }
.mood-cheer .leg-right { animation: kick-r 0.9s ease-in-out -0.05s infinite; }

/* Watch: leaning in, head craning. */
.mood-watch .body { animation: watch 1.3s ease-in-out infinite; }
.mood-watch .head { animation: watch-head 1.3s ease-in-out -0.2s infinite; }
.mood-watch .lid { animation: none; }
.mood-watch .arm-left { transform: rotate(18deg); }
.mood-watch .arm-right { transform: rotate(-18deg); }

/* Sad: droop, head hanging. */
.mood-sad .body { animation: sad 2.2s ease-in-out infinite; }
.mood-sad .head { transform: rotate(10deg) translateY(3%); }
.mood-sad .lid { transform: scaleY(0.45); animation: none; }
.mood-sad .arm-left { transform: rotate(-38deg); }
.mood-sad .arm-right { transform: rotate(38deg); }

/* Think: tilt, a hand raised. */
.mood-think .body { animation: think 2.4s ease-in-out infinite; }
.mood-think .head { transform: rotate(8deg); }
.mood-think .arm-right { transform: rotate(-70deg); }

/* Peek: leaning in from the edge, hand shading the eyes, head turning curiously. */
.mood-peek .body { animation: peek 1.2s ease-in-out infinite; }
.mood-peek .head { animation: peek-head 1.6s ease-in-out infinite; }
.mood-peek .arm-right { transform: rotate(-80deg); }

/* In the air: limbs paddling, head looking up. */
.mood-air .body { transform: rotate(-5deg); }
.mood-air .head { animation: head-drift 0.9s ease-in-out infinite; }
.mood-air .leg-left { animation: paddle 0.6s ease-in-out infinite; }
.mood-air .leg-right { animation: paddle 0.6s ease-in-out -0.3s infinite; }
.mood-air .arm-left { animation: windmill-l 0.8s ease-in-out infinite; }
.mood-air .arm-right { animation: windmill-r 0.8s ease-in-out -0.2s infinite; }
.mood-air .shadow { opacity: 0.35; }

/* Being held: legs kicking, arms up, head looking down at the ground. (The swing comes from RoamingMeeple.) */
.mood-dangle .head { transform: rotate(-6deg) translateY(2%); }
.mood-dangle .leg-left { animation: paddle 0.42s ease-in-out infinite; }
.mood-dangle .leg-right { animation: paddle 0.42s ease-in-out -0.21s infinite; }
.mood-dangle .arm-left { animation: arms-up-l 0.5s ease-in-out infinite alternate; }
.mood-dangle .arm-right { animation: arms-up-r 0.5s ease-in-out -0.25s infinite alternate; }
.mood-dangle .shadow { opacity: 0.35; }

/* Stumble: landing squash, wobble with decreasing swings, windmilling arms, shuffling feet, head following through. */
.mood-stumble .body { animation: stumble 1.1s ease-in-out forwards; }
.mood-stumble .head { animation: stumble-head 1.1s ease-in-out forwards; }
.mood-stumble .arm-left { animation: flail-l 1.1s ease-out forwards; }
.mood-stumble .arm-right { animation: flail-r 1.1s ease-out 0.06s forwards; }
.mood-stumble .leg-left { animation: shuffle-l 1.1s ease-in-out forwards; }
.mood-stumble .leg-right { animation: shuffle-r 1.1s ease-in-out 0.08s forwards; }

/* Fallen: anticipation, topple, impact squash, a little bounce, then lying dazed and breathing. */
.mood-fallen .body {
    animation:
        topple 0.8s linear forwards,
        lying 2.6s ease-in-out 0.8s infinite;
}
.mood-fallen .head { animation: fall-head 0.8s ease-out forwards, dazed 2.2s ease-in-out 0.8s infinite; }
.mood-fallen .arm-left { animation: fall-arm-l 0.8s ease-out forwards, twitch-l 2.6s ease-in-out 0.8s infinite; }
.mood-fallen .arm-right { animation: fall-arm-r 0.8s ease-out forwards; }
.mood-fallen .leg-left { animation: fall-leg-l 0.8s ease-out forwards, wiggle-l 2.8s ease-in-out 0.8s infinite; }
.mood-fallen .leg-right { animation: fall-leg-r 0.8s ease-out forwards, wiggle-r 2.8s ease-in-out 1s infinite; }
.mood-fallen .lid { animation: none; transform: scaleY(0.7); }
.mood-fallen .dizzy { display: block; animation: fade-in 0.4s 0.7s both; }
.mood-fallen .shadow { transform: translateX(-38%) scaleX(1.9); }

/* Get up: rock back, push off the floor, overshoot upright, settle, shake the head clear. */
.mood-getup .body { animation: getup 0.95s ease-in-out forwards; }
.mood-getup .head { animation: getup-head 0.95s ease-in-out forwards; }
.mood-getup .arm-left { animation: push-l 0.95s ease-in-out forwards; }
.mood-getup .arm-right { animation: push-r 0.95s ease-in-out forwards; }
.mood-getup .leg-left { animation: tuck-l 0.95s ease-in-out forwards; }
.mood-getup .leg-right { animation: tuck-r 0.95s ease-in-out forwards; }

/* ===================== Keyframes ===================== */

@keyframes breathe {
    0%, 100% { transform: scale(1, 1) rotate(-1.5deg); }
    50% { transform: scale(0.985, 1.03) rotate(1.5deg); }
}
@keyframes head-drift {
    0%, 100% { transform: rotate(-3deg); }
    50% { transform: rotate(3deg) translateY(-1%); }
}
@keyframes arm-rest-l { 0%, 100% { transform: rotate(-5deg); } 50% { transform: rotate(6deg); } }
@keyframes arm-rest-r { 0%, 100% { transform: rotate(5deg); } 50% { transform: rotate(-6deg); } }

@keyframes walk-bob {
    0%, 50%, 100% { transform: translateY(0) scale(1.03, 0.97) rotate(-2deg); }
    25%, 75% { transform: translateY(-5%) scale(0.98, 1.03) rotate(2deg); }
}
@keyframes head-bob {
    0%, 50%, 100% { transform: rotate(2.5deg) translateY(1%); }
    25%, 75% { transform: rotate(-2.5deg) translateY(-1%); }
}
@keyframes stride {
    0%, 100% { transform: rotate(-18deg); }
    50% { transform: rotate(18deg); }
}
@keyframes swing {
    0%, 100% { transform: rotate(-24deg); }
    50% { transform: rotate(24deg); }
}
@keyframes run-bob {
    0%, 50%, 100% { transform: translateY(0) scale(1.04, 0.96) rotate(9deg); }
    25%, 75% { transform: translateY(-8%) scale(0.97, 1.04) rotate(12deg); }
}
@keyframes stride-long {
    0%, 100% { transform: rotate(-28deg); }
    50% { transform: rotate(28deg); }
}
@keyframes swing-long {
    0%, 100% { transform: rotate(-40deg); }
    50% { transform: rotate(40deg); }
}

@keyframes wave-arm {
    from { transform: rotate(-100deg); }
    to { transform: rotate(-55deg); }
}
@keyframes wave-head {
    0%, 100% { transform: rotate(-6deg); }
    50% { transform: rotate(-2deg) translateY(-1%); }
}

@keyframes cheer {
    0% { transform: scale(1.12, 0.86) translateY(0); }
    18% { transform: scale(0.9, 1.12) translateY(-6%); }
    45% { transform: scale(1, 1) translateY(-32%) rotate(8deg); }
    70% { transform: scale(0.96, 1.05) translateY(-6%) rotate(-4deg); }
    82% { transform: scale(1.14, 0.84) translateY(0); }
    100% { transform: scale(1.12, 0.86) translateY(0); }
}
@keyframes cheer-head {
    0%, 100% { transform: rotate(0) translateY(2%); }
    40% { transform: rotate(-8deg) translateY(-2%); }
    75% { transform: rotate(6deg) translateY(2%); }
}
@keyframes cheer-shadow {
    0%, 82%, 100% { transform: scaleX(1.05); opacity: 1; }
    45% { transform: scaleX(0.6); opacity: 0.5; }
}
@keyframes arms-up-l { from { transform: rotate(32deg); } to { transform: rotate(58deg); } }
@keyframes arms-up-r { from { transform: rotate(-32deg); } to { transform: rotate(-58deg); } }
@keyframes kick-l { 0%, 100% { transform: rotate(0); } 45% { transform: rotate(16deg); } }
@keyframes kick-r { 0%, 100% { transform: rotate(0); } 45% { transform: rotate(-16deg); } }

@keyframes watch {
    0%, 100% { transform: rotate(-5deg) translateY(0); }
    50% { transform: rotate(-3deg) translateY(-3%); }
}
@keyframes watch-head {
    0%, 100% { transform: rotate(-4deg) translateY(1%); }
    50% { transform: rotate(-7deg) translateY(2%); }
}
@keyframes sad {
    0%, 100% { transform: scale(1.03, 0.94) rotate(7deg); }
    50% { transform: scale(1.03, 0.92) rotate(4deg) translateY(2%); }
}
@keyframes think {
    0%, 100% { transform: rotate(9deg); }
    50% { transform: rotate(5deg) translateY(-2%); }
}
@keyframes peek {
    0%, 100% { transform: rotate(14deg); }
    50% { transform: rotate(9deg) translateY(-3%); }
}
@keyframes peek-head {
    0%, 100% { transform: rotate(6deg); }
    35% { transform: rotate(-6deg); }
    70% { transform: rotate(10deg); }
}

@keyframes paddle {
    0%, 100% { transform: rotate(-14deg); }
    50% { transform: rotate(16deg); }
}
@keyframes windmill-l {
    0%, 100% { transform: rotate(20deg); }
    50% { transform: rotate(75deg); }
}
@keyframes windmill-r {
    0%, 100% { transform: rotate(-20deg); }
    50% { transform: rotate(-75deg); }
}

/* Stumble */
@keyframes stumble {
    0% { transform: scale(1.1, 0.88) rotate(0); }
    10% { transform: scale(0.98, 1.03) rotate(-15deg) translateX(-5%); }
    26% { transform: scale(1, 1) rotate(12deg) translateX(4%); }
    42% { transform: rotate(-8deg) translateX(-2%); }
    58% { transform: rotate(5deg) translateX(1%); }
    74% { transform: rotate(-2.5deg); }
    88% { transform: scale(1.03, 0.97) rotate(1deg); }
    100% { transform: scale(1, 1) rotate(0); }
}
@keyframes stumble-head {
    0% { transform: rotate(0); }
    14% { transform: rotate(12deg); }
    30% { transform: rotate(-11deg); }
    46% { transform: rotate(8deg); }
    62% { transform: rotate(-5deg); }
    80% { transform: rotate(2deg); }
    100% { transform: rotate(0); }
}
@keyframes flail-l {
    0% { transform: rotate(0); }
    12% { transform: rotate(78deg); }
    28% { transform: rotate(18deg); }
    44% { transform: rotate(66deg); }
    62% { transform: rotate(22deg); }
    80% { transform: rotate(32deg); }
    100% { transform: rotate(0); }
}
@keyframes flail-r {
    0% { transform: rotate(0); }
    14% { transform: rotate(-28deg); }
    30% { transform: rotate(-80deg); }
    48% { transform: rotate(-20deg); }
    66% { transform: rotate(-50deg); }
    100% { transform: rotate(0); }
}
@keyframes shuffle-l {
    0%, 100% { transform: rotate(0); }
    18% { transform: rotate(-16deg); }
    40% { transform: rotate(10deg); }
    62% { transform: rotate(-5deg); }
}
@keyframes shuffle-r {
    0%, 100% { transform: rotate(0); }
    24% { transform: rotate(14deg); }
    46% { transform: rotate(-9deg); }
    68% { transform: rotate(4deg); }
}

/* Fall. Order is translate → rotate → scale, so scale acts along the body:
   while lying, scaleX flattens him toward the floor and scaleY lengthens him.
   Pivoting at the feet would sink half the body into the floor, so he is lifted
   by half his width (40% of the 1:1.255 box height). */
@keyframes topple {
    0% { transform: translateY(0) rotate(0) scale(1, 1); animation-timing-function: ease-out; }
    16% { transform: translateY(0) rotate(9deg) scale(0.94, 1.06); animation-timing-function: ease-in; }
    58% { transform: translateY(-30%) rotate(-82deg) scale(1, 1.02); animation-timing-function: ease-out; }
    68% { transform: translateY(-40%) rotate(-95deg) scale(0.84, 1.1); animation-timing-function: ease-out; }
    80% { transform: translateY(-46%) rotate(-86deg) scale(1.05, 0.98); animation-timing-function: ease-in; }
    90% { transform: translateY(-40%) rotate(-92deg) scale(0.95, 1.03); animation-timing-function: ease-out; }
    100% { transform: translateY(-40%) rotate(-90deg) scale(1, 1); }
}
@keyframes lying {
    0%, 100% { transform: translateY(-40%) rotate(-90deg) scale(1, 1); }
    50% { transform: translateY(-40%) rotate(-90deg) scale(1.04, 0.99); }
}
@keyframes fall-head {
    0% { transform: rotate(0); }
    16% { transform: rotate(-6deg); }
    58% { transform: rotate(12deg); }
    70% { transform: rotate(-16deg); }
    84% { transform: rotate(7deg); }
    100% { transform: rotate(0); }
}
@keyframes dazed {
    0%, 100% { transform: rotate(-4deg); }
    50% { transform: rotate(4deg); }
}
@keyframes fall-arm-l {
    0% { transform: rotate(0); }
    18% { transform: rotate(82deg); }
    58% { transform: rotate(118deg); }
    70% { transform: rotate(38deg); }
    84% { transform: rotate(64deg); }
    100% { transform: rotate(55deg); }
}
@keyframes fall-arm-r {
    0% { transform: rotate(0); }
    18% { transform: rotate(-82deg); }
    58% { transform: rotate(-105deg); }
    70% { transform: rotate(-18deg); }
    84% { transform: rotate(-38deg); }
    100% { transform: rotate(-30deg); }
}
@keyframes twitch-l {
    0%, 70%, 100% { transform: rotate(55deg); }
    78% { transform: rotate(70deg); }
    86% { transform: rotate(50deg); }
}
@keyframes fall-leg-l {
    0% { transform: rotate(0); }
    50% { transform: rotate(18deg); }
    70% { transform: rotate(34deg); }
    86% { transform: rotate(4deg); }
    100% { transform: rotate(8deg); }
}
@keyframes fall-leg-r {
    0% { transform: rotate(0); }
    50% { transform: rotate(-24deg); }
    70% { transform: rotate(-40deg); }
    86% { transform: rotate(-6deg); }
    100% { transform: rotate(-12deg); }
}
@keyframes wiggle-l {
    0%, 80%, 100% { transform: rotate(8deg); }
    85% { transform: rotate(20deg); }
    90% { transform: rotate(4deg); }
}
@keyframes wiggle-r {
    0%, 76%, 100% { transform: rotate(-12deg); }
    82% { transform: rotate(-24deg); }
    88% { transform: rotate(-8deg); }
}
@keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }

/* Get up */
@keyframes getup {
    0% { transform: translateY(-40%) rotate(-90deg) scale(1, 1); }
    22% { transform: translateY(-38%) rotate(-97deg) scale(1.04, 0.98); }
    55% { transform: translateY(-14%) rotate(-38deg) scale(1.02, 0.98); }
    74% { transform: translateY(-2%) rotate(9deg) scale(0.93, 1.08); }
    87% { transform: translateY(0) rotate(-3deg) scale(1.07, 0.94); }
    100% { transform: translateY(0) rotate(0) scale(1, 1); }
}
@keyframes getup-head {
    0% { transform: rotate(0); }
    40% { transform: rotate(-12deg); }
    70% { transform: rotate(10deg); }
    80% { transform: rotate(-8deg); }
    88% { transform: rotate(6deg); }
    95% { transform: rotate(-3deg); }
    100% { transform: rotate(0); }
}
@keyframes push-l {
    0% { transform: rotate(55deg); }
    30% { transform: rotate(-25deg); }
    60% { transform: rotate(40deg); }
    100% { transform: rotate(0); }
}
@keyframes push-r {
    0% { transform: rotate(-30deg); }
    30% { transform: rotate(20deg); }
    60% { transform: rotate(-45deg); }
    100% { transform: rotate(0); }
}
@keyframes tuck-l {
    0% { transform: rotate(8deg); }
    40% { transform: rotate(26deg); }
    75% { transform: rotate(-8deg); }
    100% { transform: rotate(0); }
}
@keyframes tuck-r {
    0% { transform: rotate(-12deg); }
    40% { transform: rotate(-26deg); }
    75% { transform: rotate(8deg); }
    100% { transform: rotate(0); }
}
</style>
