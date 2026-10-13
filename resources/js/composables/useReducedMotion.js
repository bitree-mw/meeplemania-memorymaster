import { onBeforeUnmount, ref } from 'vue';

export function useReducedMotion() {
    const query = typeof window !== 'undefined' ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    const reduced = ref(query?.matches ?? false);
    const update = (e) => (reduced.value = e.matches);

    query?.addEventListener('change', update);
    onBeforeUnmount(() => query?.removeEventListener('change', update));

    return reduced;
}
