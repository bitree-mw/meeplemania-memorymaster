<script setup>
/** Malawi district picker, A–Z; options come from App\Platform\Districts::options(). */
defineProps({
    districts: { type: Array, required: true },
    placeholder: { type: String, default: 'Choose your district' },
    // District keys to leave out (e.g. the player's current district).
    exclude: { type: Array, default: () => [] },
});

const model = defineModel({ type: String, default: null });
</script>

<template>
    <select v-model="model" class="field">
        <option :value="null">{{ placeholder }}</option>
        <option v-for="d in districts.filter((d) => !exclude.includes(d.key))" :key="d.key" :value="d.key">{{ d.name }}</option>
    </select>
</template>
