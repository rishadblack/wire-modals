/**
 * Runtime settings are rendered as data attributes on the host root, so the browser layer
 * needs no inline script and works under a Content Security Policy.
 */
const HOST_SELECTOR = '[data-wire-modals-host]';

export function hostElement() {
    return document.querySelector(HOST_SELECTOR);
}

export function settings() {
    const host = hostElement();

    const exclusions = (host?.dataset.wireModalsDataExclusions ?? 'bs-,wire-modals-')
        .split(',')
        .map((prefix) => prefix.trim())
        .filter((prefix) => prefix !== '');

    return {
        legacyEvents: host?.dataset.wireModalsLegacyEvents === 'true',
        legacyDirective: host?.dataset.wireModalsLegacyDirective === 'true',
        dataExclusions: exclusions,
    };
}

/**
 * Livewire wraps dispatched payloads differently across versions: normalise to one object.
 */
export function eventDetail(event) {
    const detail = event?.detail;

    if (Array.isArray(detail)) {
        return detail[0] ?? {};
    }

    return detail ?? {};
}

export function emit(name, detail) {
    window.dispatchEvent(new CustomEvent(name, { detail }));
}
