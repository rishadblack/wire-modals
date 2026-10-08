import { eventDetail } from './settings.js';
import { existingModalInstance } from './bootstrap.js';

/**
 * Alpine component on the host root. It turns browser events into calls on the host's
 * $wire: a closed modal unmounts its component, and closeComponent() from PHP first hides
 * any modal that is still open so Bootstrap removes backdrops before the DOM goes away.
 */
export function registerHost(Alpine) {
    Alpine.data('wireModalsHost', () => ({
        hidden(event) {
            const { owner } = eventDetail(event);

            if (owner) {
                this.$wire.unmount(owner);
            }
        },

        dispose(event) {
            const { target: component } = eventDetail(event);

            if (!component) {
                return;
            }

            const entry = this.$el.querySelector(`[data-wire-modals-entry="${CSS.escape(component)}"]`);
            const open = entry ? Array.from(entry.querySelectorAll('.modal.show')) : [];

            if (open.length === 0) {
                this.$wire.unmount(component);

                return;
            }

            let remaining = open.length;

            open.forEach((element) => {
                element.__wireModalsDisposing = true;

                element.addEventListener('hidden.bs.modal', () => {
                    element.__wireModalsDisposing = false;

                    if (--remaining === 0) {
                        this.$wire.unmount(component);
                    }
                }, { once: true });

                existingModalInstance(element)?.hide();
            });
        },
    }));
}
