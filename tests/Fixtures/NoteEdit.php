<?php

namespace Rishadblack\WireModals\Tests\Fixtures;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Rishadblack\WireModals\Attributes\ModalAction;
use Rishadblack\WireModals\Traits\WithModals;

class NoteEdit extends Component
{
    use WithModals;

    /**
     * Actions refused by authorizeModalAction() in the current test.
     *
     * @var array<int, string>
     */
    public static array $denyActions = [];

    #[Locked]
    public int|string|null $note_id = null;

    public string $title = '';

    public ?string $folder = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $calls = [];

    /**
     * @param  array<string, mixed>  $data
     */
    #[ModalAction(default: true)]
    public function openNoteModal(array $data = []): void
    {
        $this->calls[] = $data;
        $this->note_id = $data['id'] ?? null;
        $this->title = $this->note_id ? "Note {$this->note_id}" : 'New note';
        $this->folder = isset($data['data']['folder']) ? (string) $data['data']['folder'] : null;

        $this->modalOpen('NoteModal');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function openOptionsModal(array $data = []): void
    {
        $this->calls[] = $data;

        $this->modalOpen('OptionsModal', keepComponent: true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[ModalAction]
    public function noteDelete(array $data = []): void
    {
        $this->calls[] = $data;

        if (empty($data['isConfirmed'])) {
            return;
        }

        $this->title = "Deleted {$data['id']}";

        $this->closeComponent();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notAnAction(array $data = []): void
    {
        $this->calls[] = $data;
    }

    public function save(): void
    {
        $this->modalClose('NoteModal');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function authorizeModalAction(string $action, array $payload): bool
    {
        return ! in_array($action, static::$denyActions, true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function secretHelper(array $data = []): void
    {
        $this->calls[] = $data;
    }

    public function render(): View
    {
        return view('fixtures::note-edit');
    }
}
