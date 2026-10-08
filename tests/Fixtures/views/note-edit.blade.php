<div>
    <x-wire-modals::modal id="NoteModal" title="Note">
        <x-slot:button>
            <button type="button" class="btn btn-sm btn-primary" wire:click="save">Save</button>
        </x-slot:button>
        <p class="note-title">{{ $title }}</p>
        <p class="note-folder">{{ $folder }}</p>
        <x-wire-modals::trigger component="notes.note-edit" action="openOptionsModal" class="btn btn-link">Options</x-wire-modals::trigger>
    </x-wire-modals::modal>

    <x-wire-modals::modal id="OptionsModal" size="sm" :close-button="false">
        <p>Options</p>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Done</button>
        </x-slot:footer>
    </x-wire-modals::modal>
</div>
