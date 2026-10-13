import { onBeforeUnmount, ref, watch } from 'vue';

/** Reactive width of an element (via ResizeObserver), starting from a fallback. */
export function useElementWidth(fallback = 360) {
    const el = ref(null);
    const width = ref(fallback);
    const observer = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(([entry]) => (width.value = Math.floor(entry.contentRect.width))) : null;

    watch(el, (node, old) => {
        if (old) observer?.unobserve(old);
        if (node) observer?.observe(node);
    });
    onBeforeUnmount(() => observer?.disconnect());

    return { el, width };
}
