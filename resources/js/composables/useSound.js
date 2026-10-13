import { ref } from 'vue';
import { sounds } from '@/game/audio';

const enabled = ref(sounds.enabled);

export function useSound() {
    function toggle() {
        sounds.setEnabled(!enabled.value);
        enabled.value = sounds.enabled;
        if (enabled.value) sounds.click();
    }

    return { enabled, toggle, sounds };
}
