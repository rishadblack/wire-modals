<?php

use Illuminate\Support\Facades\Blade;

it('renders a Bootstrap modal shell with the package defaults', function () {
    $html = Blade::render('<x-wire-modals::modal id="NoteModal" title="Note"><p>Body</p></x-wire-modals::modal>');

    expect($html)->toContain('id="NoteModal"')
        ->toContain('class="modal fade"')
        ->toContain('wire:ignore.self')
        ->toContain('x-data="wireModalsModal"')
        ->toContain('x-on:wire-modals:open.window="open"')
        ->toContain('x-on:wire-modals:close.window="close"')
        ->toContain('data-bs-backdrop="static"')
        ->toContain('data-bs-keyboard="false"')
        ->toContain('aria-labelledby="NoteModal-title"')
        ->toContain('<h5 class="modal-title" id="NoteModal-title">Note</h5>')
        ->toContain('btn-close')
        ->toContain('<p>Body</p>')
        ->not->toContain('<style')
        ->not->toContain('modal-open.camel');
});

it('applies size, centered, scrollable and fullscreen options', function () {
    $html = Blade::render('<x-wire-modals::modal id="M" size="xl" :centered="true" :scrollable="true" fullscreen="md">x</x-wire-modals::modal>');

    expect($html)->toContain('class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-md-down"');
});

it('renders header actions and footer slots and can hide the close button', function () {
    $html = Blade::render(<<<'BLADE'
        <x-wire-modals::modal id="M" :close-button="false" :backdrop="false" :keyboard="true">
            <x-slot:button class="header-actions"><button>Save</button></x-slot:button>
            Body
            <x-slot:footer class="justify-content-start"><button>Done</button></x-slot:footer>
        </x-wire-modals::modal>
        BLADE);

    expect($html)->toContain('<span class="header-actions"><button>Save</button></span>')
        ->toMatch('/class="(modal-footer justify-content-start|justify-content-start modal-footer)"/')
        ->toContain('data-bs-backdrop="false"')
        ->toContain('data-bs-keyboard="true"')
        ->not->toContain('btn-close');
});

it('merges extra attributes onto the modal root', function () {
    $html = Blade::render('<x-wire-modals::modal id="M" class="custom" data-test="1">x</x-wire-modals::modal>');

    expect($html)->toMatch('/class="(modal fade custom|custom modal fade)"/')->toContain('data-test="1"');
});

it('listens to the legacy browser events when legacy mode is on', function () {
    config()->set('wire-modals.legacy.events', true);

    $html = Blade::render('<x-wire-modals::modal id="M">x</x-wire-modals::modal>');

    expect($html)->toContain('x-on:modal-open.camel.window="open"')
        ->toContain('x-on:modal-close.camel.window="close"')
        ->toContain('data-wire-modals-legacy="true"');
});

it('uses config defaults for size, centered and scrollable', function () {
    config()->set('wire-modals.modal.size', 'lg');
    config()->set('wire-modals.modal.centered', true);

    $html = Blade::render('<x-wire-modals::modal id="M">x</x-wire-modals::modal>');

    expect($html)->toContain('class="modal-dialog modal-lg modal-dialog-centered"');
});
