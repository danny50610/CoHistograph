import './bootstrap';
import { createApp } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import RevisionGraphPreview from './Pages/Revisions/Partials/RevisionGraphPreview.vue';

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

function mountRevisionGraphPreviews() {
    document.querySelectorAll('[data-revision-graph-root]').forEach((root) => {
        const jsonEl = root.querySelector('[data-revision-graph-json]');
        const mountEl = root.querySelector('[data-revision-graph-mount]');

        if (!jsonEl || !mountEl) {
            return;
        }

        let app = null;
        const mount = () => {
            if (app !== null) {
                return;
            }

            let graph = { vertices: [], edges: [] };
            try {
                graph = JSON.parse(jsonEl.textContent || '{}');
            } catch {
                graph = { vertices: [], edges: [] };
            }

            app = createApp(RevisionGraphPreview, {
                vertices: graph.vertices ?? [],
                edges: graph.edges ?? [],
            });
            app.mount(mountEl);
        };

        document.getElementById('revision-graph-tab')?.addEventListener('shown.bs.tab', mount);
    });
}

if (hasInertiaPage()) {
    createInertiaApp({
        resolve: (name) => {
            const pages = import.meta.glob('./Pages/**/*.vue');

            return pages[`./Pages/${name}.vue`]();
        },
    });
} else {
    mountRevisionGraphPreviews();
}

