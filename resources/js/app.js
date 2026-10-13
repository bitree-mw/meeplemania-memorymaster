import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { syncQueue } from '@/game/sync';

// Results saved on this device during an earlier visit (e.g. while offline) sync now.
syncQueue.flushAll();

createInertiaApp({
    title: (title) => (title ? `${title} · Meeple Mania` : 'Meeple Mania Games'),
    // Pages are code-split: each one loads only when first visited.
    resolve: async (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const page = await pages[`./Pages/${name}.vue`]();
        // Admin pages declare their own layout; everything else uses the game shell.
        page.default.layout ??= AppLayout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: { color: '#f59e0b' },
});
