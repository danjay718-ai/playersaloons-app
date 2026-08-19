<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Identity\Actions\ApplyComplianceBlockAction;
use App\Modules\Identity\Actions\RevokeComplianceBlockAction;
use App\Modules\Identity\Models\ComplianceBlock;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class ComplianceAdmin extends AdminComponent
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'active';

    public ?int $selectedUserId = null;

    public ?int $selectedBlockId = null;

    public string $category = 'platform_abuse';

    public string $reason = '';

    public string $expiresAt = '';

    public string $revocationReason = '';

    public bool $showApplyModal = false;

    public bool $showRevokeModal = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openApply(int|string $userId): void
    {
        if (filter_var($userId, FILTER_VALIDATE_INT) === false || (int) $userId < 1) {
            return;
        }

        $this->resetValidation();
        $this->selectedUserId = (int) $userId;
        $this->reason = '';
        $this->expiresAt = '';
        $this->showApplyModal = true;
    }

    public function applyBlock(ApplyComplianceBlockAction $action): void
    {
        $data = $this->validate([
            'selectedUserId' => ['required', 'integer', 'exists:users,id'],
            'category' => ['required', 'in:fraud,chargeback,platform_abuse,identity_risk,legal_restriction'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'expiresAt' => ['nullable', 'date', 'after:now'],
        ]);

        $actor = Auth::user();
        abort_unless($actor, 403);

        try {
            $action->execute(
                User::findOrFail($data['selectedUserId']),
                $actor,
                $data['category'],
                $data['reason'],
                $data['expiresAt'] !== '' ? Carbon::parse($data['expiresAt']) : null,
            );
            $this->showApplyModal = false;
            session()->flash('success', 'Compliance block applied.');
        } catch (\Throwable $exception) {
            $this->addError('reason', $this->safeError($exception, 'Unable to update the compliance restriction.'));
        }
    }

    public function openRevoke(int $blockId): void
    {
        $this->resetValidation();
        $this->selectedBlockId = $blockId;
        $this->revocationReason = '';
        $this->showRevokeModal = true;
    }

    public function revokeBlock(RevokeComplianceBlockAction $action): void
    {
        $data = $this->validate([
            'selectedBlockId' => ['required', 'integer', 'exists:compliance_blocks,id'],
            'revocationReason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $actor = Auth::user();
        abort_unless($actor, 403);

        $action->execute(ComplianceBlock::findOrFail($data['selectedBlockId']), $actor, $data['revocationReason']);
        $this->showRevokeModal = false;
        session()->flash('success', 'Compliance block revoked.');
    }

    public function render()
    {
        $blocks = ComplianceBlock::query()
            ->with(['user', 'creator', 'revoker'])
            ->when($this->search, fn ($query) => $query->whereHas('user', fn ($users) => $users
                ->where('username', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')))
            ->when($this->statusFilter === 'active', fn ($query) => $query->active())
            ->when($this->statusFilter === 'revoked', fn ($query) => $query->whereNotNull('revoked_at'))
            ->when($this->statusFilter === 'expired', fn ($query) => $query->whereNull('revoked_at')->where('expires_at', '<=', now()))
            ->latest()
            ->paginate(15);

        $eligibleUsers = User::query()
            ->role('PLAYER')
            ->whereDoesntHave('complianceBlocks', fn ($query) => $query->active())
            ->orderBy('username')
            ->get(['id', 'username', 'email']);

        return view('livewire.admin.compliance-admin', compact('blocks', 'eligibleUsers'))
            ->layout('components.layouts.admin', ['admin_title' => 'Compliance & Blacklisting']);
    }
}
