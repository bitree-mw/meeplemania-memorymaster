<script setup>
import { computed } from 'vue';
import { Heart, HeartCrack, Play } from 'lucide-vue-next';
import FloatingNext from '@/Components/Game/FloatingNext.vue';
import MeepleMascot from '@/Components/Mascot/MeepleMascot.vue';
import { useElementWidth } from '@/composables/useElementWidth';
import { BOARDS } from '@/game/boards';
import { explainLoss } from '@/game/coach';

/** A round was lost but lives remain: show what slipped, then try a new puzzle at the same level. */
const props = defineProps({
    game: { type: String, required: true },
    round: { type: Object, required: true },
    roundState: { type: Object, default: null },
    lives: { type: Number, required: true },
    livesTotal: { type: Number, required: true },
});

defineEmits(['next']);

const board = computed(() => BOARDS[props.game]);
const { el: reviewEl, width: reviewWidth } = useElementWidth(300);
</script>

<template>
    <section class="card mx-auto w-full max-w-lg p-5 text-center motion-safe:animate-rise sm:p-7" aria-labelledby="life-lost-title">
        <div class="flex items-center justify-center gap-3">
            <MeepleMascot mood="sad" :size="52" />
            <div class="text-left">
                <h2 id="life-lost-title" class="flex items-center gap-2 text-2xl font-extrabold text-navy-deep"><HeartCrack :size="24" class="text-ruby" /> Life lost!</h2>
                <p class="flex items-center gap-1" :aria-label="`${lives} of ${livesTotal} lives left`">
                    <Heart v-for="i in livesTotal" :key="i" :size="18" :class="i <= lives ? 'fill-ruby text-ruby' : 'text-outline-soft'" aria-hidden="true" />
                </p>
            </div>
        </div>
        <p class="mt-3 font-medium text-ink-soft">{{ explainLoss(game, round) }}</p>

        <div ref="reviewEl" class="mt-4 flex justify-center">
            <component :is="board" :spec="round.spec" phase="review" :round-state="roundState" :inputs="round.inputs" :timings="round.timings" review :box="{ w: Math.min(reviewWidth, 400), h: 260 }" />
        </div>

        <!-- On phones the floating button below takes this role. -->
        <button type="button" class="btn btn-primary btn-lg mt-5 hidden w-full sm:inline-flex" autofocus @click="$emit('next')">
            <Play :size="20" fill="currentColor" aria-hidden="true" /> Try a new puzzle (level {{ round.spec.level }})
        </button>

        <FloatingNext :level="round.spec.level" kind="retry" @click="$emit('next')" />
    </section>
</template>
