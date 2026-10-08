import { hostElement, settings } from './settings.js';
import { payloadFromElement } from './payload.js';

const LOADING_ATTRIBUTE = 'data-wire-modals-loading';
const TIMEOUT_MS = 10000;

/**
 * Registers the `wire:modal` directive. Livewire invokes a directive once per matching
 * attribute, so `wire:modal.action` and `wire:modal.id` would bind too: only the bare
 * attribute (no modifiers) attaches the click handler.
 */
export function registerTrigger(Livewire) {
    Livewire.directive('modal', ({ el, directive, cleanup }) => {
        if (directive.modifiers.length > 0) {
            return;
        }

        const onClick = (event) => {
            event.preventDefault();

            const payload = payloadFromElement(el, settings().dataExclusions);

            if (payload) {
                sendToHost(el, payload);
            }
        };

        el.addEventListener('click', onClick);

        cleanup(() => el.removeEventListener('click', onClick));
    });
}

/**
 * Hand a payload to the host component. Shared by the modern and the legacy directive.
 */
export function sendToHost(element, payload) {
    if (element.__wireModalsPending) {
        return;
    }

    const host = hostElement();

    if (!host) {
        console.error('[wire-modals] No host found. Add <livewire:wire-modals.host /> to the layout.');

        return;
    }

    const wire = window.Livewire.find(host.getAttribute('wire:id'));

    if (!wire) {
        console.error('[wire-modals] The host element is not initialised yet.');

        return;
    }

    beginLoading(element);

    const timeout = new Promise((resolve) => setTimeout(resolve, TIMEOUT_MS));

    Promise.race([wire.load(payload), timeout])
        .catch((error) => console.error('[wire-modals] load failed', error))
        .finally(() => endLoading(element));
}

function beginLoading(element) {
    element.__wireModalsPending = true;
    element.setAttribute(LOADING_ATTRIBUTE, 'true');
    element.setAttribute('aria-busy', 'true');

    if ('disabled' in element) {
        element.disabled = true;
    }
}

function endLoading(element) {
    element.__wireModalsPending = false;
    element.removeAttribute(LOADING_ATTRIBUTE);
    element.removeAttribute('aria-busy');

    if ('disabled' in element) {
        element.disabled = false;
    }
}
