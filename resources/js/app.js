import './bootstrap';
import { createInertiaApp } from '@inertiajs/vue3';

// TODO: 注入 axios ?
// https://inertiajs.com/docs/v3/installation/client-side-setup#http-client

// Blade 頁面也會載入此 entry；僅在有 Inertia page component 時才啟動。
function hasInertiaPage(id = 'app') {
    const scriptEl = document.querySelector(`script[data-page="${id}"][type="application/json"]`);

    if (!scriptEl?.textContent) {
        return false;
    }

    try {
        const page = JSON.parse(scriptEl.textContent);

        return Boolean(page?.component);
    } catch {
        return false;
    }
}

if (hasInertiaPage()) {
    createInertiaApp({
        resolve: (name) => {
            const pages = import.meta.glob('./Pages/**/*.vue');

            return pages[`./Pages/${name}.vue`]();
        },
    });
}

