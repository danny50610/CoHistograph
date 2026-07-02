import './bootstrap';
import { createInertiaApp } from '@inertiajs/vue3';

// TODO: 注入 axios ?
// https://inertiajs.com/docs/v3/installation/client-side-setup#http-client

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },
});
