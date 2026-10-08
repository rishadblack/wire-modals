/**
 * Access to Bootstrap's Modal class. Applications that bundle Bootstrap under another name
 * can set `window.wireModals = { bootstrap: () => myBootstrap }` before this script runs.
 */
let warned = false;

export function bootstrap() {
    const instance = window.wireModals?.bootstrap?.() ?? window.bootstrap;

    if (!instance?.Modal) {
        if (!warned) {
            warned = true;
            console.error('[wire-modals] Bootstrap 5 was not found on window.bootstrap. Import bootstrap before wire-modals.');
        }

        return null;
    }

    return instance;
}

export function modalInstance(element) {
    return bootstrap()?.Modal.getOrCreateInstance(element) ?? null;
}

export function existingModalInstance(element) {
    return bootstrap()?.Modal.getInstance(element) ?? null;
}
