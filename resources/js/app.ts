/*
 * Single entry point. Each page module is loaded on demand; there are no
 * analytics, no service worker and no browser storage of any secret material.
 */
const pages: Record<string, () => Promise<{ init: () => void }>> = {
    create: () => import('./pages/create-secret'),
    open: () => import('./pages/open-secret'),
    manage: () => import('./pages/manage-secret'),
};

const loadPage = pages[document.body.dataset.page ?? ''];

if (loadPage !== undefined) {
    void loadPage().then((page) => page.init());
}
