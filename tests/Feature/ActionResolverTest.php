<?php

use Rishadblack\WireModals\Support\ActionResolver;
use Rishadblack\WireModals\Tests\Fixtures\NoteEdit;
use Rishadblack\WireModals\Tests\Fixtures\TagEdit;

it('allows methods matching the configured patterns', function () {
    expect(ActionResolver::make()->allows(new TagEdit, 'openTagModal'))->toBeTrue();
});

it('allows methods marked with the ModalAction attribute', function () {
    expect(ActionResolver::make()->allows(new NoteEdit, 'noteDelete'))->toBeTrue();
});

it('denies application methods that neither match nor carry the attribute', function () {
    expect(ActionResolver::make()->allows(new NoteEdit, 'notAnAction'))->toBeFalse()
        ->and(ActionResolver::make()->allows(new NoteEdit, 'save'))->toBeFalse()
        ->and(ActionResolver::make()->allows(new TagEdit, 'tagDelete'))->toBeFalse();
});

it('denies protected, static, magic and unknown methods', function () {
    $resolver = ActionResolver::make();

    expect($resolver->allows(new NoteEdit, 'secretHelper'))->toBeFalse()
        ->and($resolver->allows(new NoteEdit, 'modalTrigger'))->toBeFalse()
        ->and($resolver->allows(new NoteEdit, '__dispatch'))->toBeFalse()
        ->and($resolver->allows(new NoteEdit, '__call'))->toBeFalse()
        ->and($resolver->allows(new NoteEdit, 'nope'))->toBeFalse();
});

it('never allows Livewire or package methods even when a pattern matches them', function (string $method) {
    config()->set('wire-modals.action_patterns', ['*']);

    expect(ActionResolver::make()->allows(new NoteEdit, $method))->toBeFalse();
})->with([
    'render', 'Render', 'mount', 'dispatch', 'skipRender', 'redirect', 'js', 'stream',
    'runModalAction', 'modalOpen', 'modalClose', 'closeComponent', 'onWireModalsAlreadyMounted', 'mountWithModals',
]);

it('honours custom patterns', function () {
    config()->set('wire-modals.action_patterns', ['open*Modal', '*Delete']);

    expect(ActionResolver::make()->allows(new TagEdit, 'tagDelete'))->toBeTrue()
        ->and(ActionResolver::make()->allows(new TagEdit, 'openTagModal'))->toBeTrue();
});

it('works with class names as well as instances', function () {
    $resolver = ActionResolver::make();

    expect($resolver->allows(NoteEdit::class, 'noteDelete'))->toBeTrue()
        ->and($resolver->allows(NoteEdit::class, 'render'))->toBeFalse()
        ->and($resolver->defaultAction(NoteEdit::class))->toBe('openNoteModal');
});

it('resolves a differently cased request to the canonical method name', function () {
    $resolver = ActionResolver::make();

    expect($resolver->resolve(new NoteEdit, 'NOTEDELETE'))->toBe('noteDelete')
        ->and($resolver->resolve(new TagEdit, 'OpenTagModal'))->toBe('openTagModal')
        ->and($resolver->resolve(new NoteEdit, 'notAnAction'))->toBeNull();
});

it('finds the default action', function () {
    $resolver = ActionResolver::make();

    expect($resolver->defaultAction(new NoteEdit))->toBe('openNoteModal')
        ->and($resolver->defaultAction(new TagEdit))->toBeNull();
});
