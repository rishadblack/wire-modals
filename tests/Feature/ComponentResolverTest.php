<?php

use Rishadblack\WireModals\Exceptions\ComponentNotAllowedException;
use Rishadblack\WireModals\Support\ComponentResolver;
use Rishadblack\WireModals\Tests\Fixtures\NoteEdit;

it('resolves a registered name to its class without mounting it', function () {
    expect(ComponentResolver::make()->classFor('notes.note-edit'))->toBe(NoteEdit::class)
        ->and(ComponentResolver::make()->classFor('does.not-exist'))->toBeNull();
});

it('allows a registered component that uses the trait', function () {
    expect(ComponentResolver::make()->assertAllowed('notes.note-edit'))->toBe(NoteEdit::class);
});

it('refuses components without the trait', function () {
    ComponentResolver::make()->assertAllowed('plain');
})->throws(ComponentNotAllowedException::class, 'does not use the WithModals trait');

it('refuses unknown components', function () {
    ComponentResolver::make()->assertAllowed('does.not-exist');
})->throws(ComponentNotAllowedException::class, 'not a registered');

it('refuses components outside the allow list', function () {
    config()->set('wire-modals.allowed_components', ['tags.*']);

    ComponentResolver::make()->assertAllowed('notes.note-edit');
})->throws(ComponentNotAllowedException::class, 'allowed_components');

it('refuses class names unless enabled', function () {
    ComponentResolver::make()->assertAllowed(NoteEdit::class);
})->throws(ComponentNotAllowedException::class, 'class name');
