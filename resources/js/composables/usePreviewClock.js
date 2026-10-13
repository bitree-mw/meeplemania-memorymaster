import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Milliseconds elapsed since `startsAt()` (a performance.now() timestamp),
 * updated every animation frame while `active()` is true. Negative before the
 * preview begins (the short "get ready" lead).
 */
export function usePreviewClock(startsAt, active) {
    const elapsed = ref(-1);
    let frame = null;

    const loop = () => {
        elapsed.value = performance.now() - startsAt();
        frame = requestAnimationFrame(loop);
    };

    watch(
        active,
        (on) => {
            cancelAnimationFrame(frame);
            if (on) loop();
        },
        { immediate: true },
    );

    onBeforeUnmount(() => cancelAnimationFrame(frame));

    return elapsed;
}
