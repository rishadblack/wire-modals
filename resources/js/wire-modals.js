/**
 * wire-modals browser layer.
 *
 * Import this file after Livewire is available (with Vite, in resources/js/app.js). It
 * registers the `wire:modal` directive, the Alpine components used by the host and the
 * modal Blade component, and (when enabled in config) the legacy `wire:load:component`
 * directive. Bootstrap 5 must be loaded as `window.bootstrap`.
 */
import { registerTrigger } from './src/trigger.js';
import { registerModal } from './src/modal.js';
import { registerHost } from './src/host.js';
import { registerLegacy } from './src/legacy.js';

document.addEventListener('alpine:init', () => {
    registerModal(window.Alpine);
    registerHost(window.Alpine);
});

document.addEventListener('livewire:init', () => {
    registerTrigger(window.Livewire);
    registerLegacy(window.Livewire);
});
