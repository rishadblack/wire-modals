import { emit, eventDetail } from './settings.js';
import { existingModalInstance, modalInstance } from './bootstrap.js';

const BASE_Z_INDEX = 1055;
const STACK_STEP = 20;

/**
 * Alpine component behind <x-wire-modals::modal>. Each modal listens for the open / close
 * browser events and acts only when the event names its own id.
 *
 * Modals may stack (a component opens a second and third modal on top of the first, or one
 * component opens another component's modal). Bootstrap does not manage that on its own, so
 * each newly shown modal and its backdrop are raised above the ones already open, and the
 * body keeps its "modal-open" state while any modal remains visible.
 */
export function registerModal(Alpine) {
    Alpine.data('wireModalsModal', () => ({
        component: null,

        init() {
            this.onHidden = () => this.hidden();
            this.$el.addEventListener('hidden.bs.modal', this.onHidden);
        },

        destroy() {
            this.$el.removeEventListener('hidden.bs.modal', this.onHidden);
        },

        open(event) {
            const detail = eventDetail(event);

            if (detail.modal !== this.$el.id) {
                return;
            }

            // Modern events carry "owner"; the legacy modalOpen event carried "component".
            this.component = detail.owner ?? detail.component ?? null;

            const openBefore = document.querySelectorAll('.modal.show').length;

            modalInstance(this.$el)?.show();

            if (openBefore > 0) {
                raiseAboveOpenModals(this.$el, openBefore);
            }

            emit('wire-modals:opened', { modal: this.$el.id, owner: this.component });

            if (this.$el.dataset.wireModalsLegacy === 'true') {
                emit('set-tab', { modalId: this.$el.id });
            }
        },

        close(event) {
            const detail = eventDetail(event);

            if (detail.modal !== this.$el.id) {
                return;
            }

            existingModalInstance(this.$el)?.hide();
        },

        hidden() {
            const component = this.component;

            this.component = null;
            this.$el.style.zIndex = '';

            keepBodyLockedWhileModalsRemain();

            if (component && !this.$el.__wireModalsDisposing) {
                emit('wire-modals:hidden', { modal: this.$el.id, owner: component });
            }
        },
    }));
}

/**
 * Bootstrap appends the backdrop synchronously inside show(), so the last backdrop in the
 * document belongs to the modal that was just shown.
 */
function raiseAboveOpenModals(element, openBefore) {
    const zIndex = BASE_Z_INDEX + openBefore * STACK_STEP;

    element.style.zIndex = String(zIndex);

    const backdrops = document.querySelectorAll('.modal-backdrop');
    const backdrop = backdrops[backdrops.length - 1];

    if (backdrop) {
        backdrop.style.zIndex = String(zIndex - 10);
    }
}

function keepBodyLockedWhileModalsRemain() {
    if (document.querySelector('.modal.show')) {
        document.body.classList.add('modal-open');
    }
}
