import { settings } from './settings.js';
import { payloadFromLegacyExpression } from './payload.js';
import { sendToHost } from './trigger.js';

/**
 * The historical `wire:load:component="{action:'...', component:'...'}"` directive. Only
 * active when `wire-modals.legacy.directive` is on. The expression is converted to JSON and
 * parsed; it is never evaluated.
 */
export function registerLegacy(Livewire) {
    Livewire.directive('load:component', ({ el, directive, cleanup }) => {
        if (directive.modifiers.length > 0) {
            return;
        }

        const onClick = (event) => {
            if (!settings().legacyDirective) {
                return;
            }

            event.preventDefault();

            let payload;

            try {
                payload = payloadFromLegacyExpression(directive.expression);
            } catch (error) {
                console.error('[wire-modals] Could not read the legacy wire:load:component expression.', el, error);

                return;
            }

            sendToHost(el, payload);
        };

        el.addEventListener('click', onClick);

        cleanup(() => el.removeEventListener('click', onClick));
    });
}
