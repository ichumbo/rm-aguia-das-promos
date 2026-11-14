<?php

namespace App\Livewire\Groups;

use App\Models\AuditLog;
use App\Models\Group;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showFormModal = false;

    public bool $showReplaceLinkModal = false;

    public ?int $editingId = null;

    public ?int $replacingGroupId = null;

    public string $name = '';

    public string $category = '';

    public string $description = '';

    public string $invite_url = '';

    public string $capacity_note = '';

    public bool $is_active = true;

    public string $newInviteUrl = '';

    public string $replaceReason = '';

    public ?string $flashMessage = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEdit(int $groupId): void
    {
        $group = Group::query()->findOrFail($groupId);
        $this->editingId = $group->id;
        $this->name = $group->name;
        $this->category = $group->category;
        $this->description = (string) ($group->description ?? '');
        $this->invite_url = $group->invite_url;
        $this->capacity_note = (string) ($group->capacity_note ?? '');
        $this->is_active = $group->is_active;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'category' => ['required', 'string', 'min:2', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'invite_url' => ['required', 'url', 'starts_with:https://'],
            'capacity_note' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ], [
            'invite_url.starts_with' => 'O link oficial de convite deve iniciar com https://',
        ]);

        if ($this->editingId !== null) {
            $group = Group::query()->findOrFail($this->editingId);
            $group->update($validated);
            AuditLog::record('group.updated', $group, ['name' => $group->name]);
            $this->flashMessage = 'Grupo atualizado com sucesso.';
        } else {
            $group = Group::create(array_merge($validated, [
                'created_by' => auth()->id(),
            ]));
            AuditLog::record('group.created', $group, ['name' => $group->name]);
            $this->flashMessage = 'Grupo cadastrado com sucesso.';
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $groupId): void
    {
        $group = Group::query()->findOrFail($groupId);
        $group->update(['is_active' => ! $group->is_active]);
        AuditLog::record('group.toggled_active', $group, ['is_active' => $group->is_active]);
        $this->flashMessage = $group->is_active ? 'Grupo ativado.' : 'Grupo inativado.';
    }

    public function openReplaceLink(int $groupId): void
    {
        $this->replacingGroupId = $groupId;
        $this->newInviteUrl = '';
        $this->replaceReason = '';
        $this->showReplaceLinkModal = true;
    }

    public function replaceRevokedLink(): void
    {
        $this->validate([
            'newInviteUrl' => ['required', 'url', 'starts_with:https://'],
            'replaceReason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $group = Group::query()->findOrFail($this->replacingGroupId);

        DB::transaction(function () use ($group): void {
            $group->update([
                'invite_url' => trim($this->newInviteUrl),
                'revoked_at' => null,
                'is_active' => true,
            ]);

            AuditLog::record('group.link_replaced', $group, [
                'reason' => trim($this->replaceReason),
            ]);
        });

        $this->showReplaceLinkModal = false;
        $this->replacingGroupId = null;
        $this->flashMessage = 'Link oficial substituído e grupo reativado com auditoria.';
    }

    public function markLinkRevoked(int $groupId): void
    {
        $group = Group::query()->findOrFail($groupId);
        $group->update([
            'is_active' => false,
            'revoked_at' => now(),
        ]);
        AuditLog::record('group.link_revoked', $group);
        $this->flashMessage = 'Link marcado como revogado e grupo pausado para novos convites.';
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->category = '';
        $this->description = '';
        $this->invite_url = '';
        $this->capacity_note = '';
        $this->is_active = true;
    }

    public function render()
    {
        $groups = Group::query()
            ->when($this->search !== '', function ($query): void {
                $term = '%' . trim($this->search) . '%';
                $query->where(function ($sub) use ($term): void {
                    $sub->where('name', 'like', $term)
                        ->orWhere('category', 'like', $term);
                });
            })
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true)->whereNull('revoked_at'))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where(function ($sub): void {
                $sub->where('is_active', false)->orWhereNotNull('revoked_at');
            }))
            ->withCount('contacts')
            ->orderBy('name')
            ->paginate(12);

        return view('livewire.groups.index', [
            'groups' => $groups,
        ]);
    }
}
