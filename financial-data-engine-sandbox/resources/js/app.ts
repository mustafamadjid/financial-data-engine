import { createInertiaApp } from '@inertiajs/vue3';
import { VueQueryPlugin } from '@tanstack/vue-query';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, type DefineComponent } from 'vue';
import { createQueryClient } from './bootstrap/queryClient';

const queryClient = createQueryClient();

const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue');

void createInertiaApp({
    resolve: (name) => resolvePageComponent<DefineComponent>(`./Pages/${name}.vue`, pages),
    setup({ el, App, props, plugin }) {
        if (el === null) {
            return;
        }

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(VueQueryPlugin, { queryClient })
            .mount(el);
    },
});
