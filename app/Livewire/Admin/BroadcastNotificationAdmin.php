<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Community\Models\BroadcastMessage;
use App\Modules\Operations\Services\AdminDeletionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class BroadcastNotificationAdmin extends AdminComponent
{
    use WithFileUploads;
    use WithPagination;

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('broadcast_messages.manage'), 403);
    }

    protected $paginationTheme = 'tailwind';

    // Form fields
    public string $title = '';

    public string $message = '';

    public string $displayType = BroadcastMessage::DISPLAY_FEED;

    public string $targetSurface = BroadcastMessage::SURFACE_BOTH;

    public string $badgeLabel = '';

    public string $mediaType = BroadcastMessage::MEDIA_NONE;

    public string $mediaUrl = '';

    public mixed $mediaFile = null;

    public bool $isVideoOnly = false;

    public string $ctaLabel = '';

    public string $ctaUrl = '';

    public string $startsAt = '';

    public string $endsAt = '';

    // Modal state
    public bool $showFormModal = false;

    public bool $showConfirmModal = false;

    public ?int $editingId = null;

    public ?int $confirmTargetId = null;

    public string $confirmAction = ''; // 'delete' | 'expire'

    // Search
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMediaType(string $value): void
    {
        if ($value !== BroadcastMessage::MEDIA_VIDEO) {
            $this->isVideoOnly = false;
        }
        if ($value === BroadcastMessage::MEDIA_NONE) {
            $this->mediaUrl = '';
            $this->mediaFile = null;
        }
    }

    // ── CRUD ────────────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $broadcast = BroadcastMessage::findOrFail($id);

        $this->resetErrorBag();
        $this->editingId = $id;
        $this->title = $broadcast->title;
        $this->message = $broadcast->message;
        $this->displayType = $broadcast->display_type ?: BroadcastMessage::DISPLAY_FEED;
        $this->targetSurface = $broadcast->target_surface ?: BroadcastMessage::SURFACE_BOTH;
        $this->badgeLabel = (string) ($broadcast->badge_label ?? '');
        $this->mediaType = $broadcast->media_type ?: BroadcastMessage::MEDIA_NONE;
        $this->mediaUrl = (string) ($broadcast->media_url ?? '');
        $this->mediaFile = null;
        $this->isVideoOnly = (bool) $broadcast->is_video_only;
        $this->ctaLabel = (string) ($broadcast->cta_label ?? '');
        $this->ctaUrl = (string) ($broadcast->cta_url ?? '');
        $this->startsAt = $broadcast->starts_at ? Carbon::parse($broadcast->starts_at)->format('Y-m-d\TH:i') : '';
        $this->endsAt = $broadcast->ends_at ? Carbon::parse($broadcast->ends_at)->format('Y-m-d\TH:i') : '';
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $mediaOnlyEnabled = $this->displayType !== BroadcastMessage::DISPLAY_FEED
            && $this->mediaType !== BroadcastMessage::MEDIA_NONE
            && $this->isVideoOnly;

        $mediaFileRule = match ($this->mediaType) {
            BroadcastMessage::MEDIA_IMAGE => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            BroadcastMessage::MEDIA_VIDEO => ['nullable', 'file', 'mimetypes:video/mp4,video/webm', 'max:20480'],
            default => ['nullable'],
        };

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => [$mediaOnlyEnabled ? 'nullable' : 'required', 'string', 'max:2000'],
            'displayType' => ['required', Rule::in([BroadcastMessage::DISPLAY_FEED, BroadcastMessage::DISPLAY_POPUP, BroadcastMessage::DISPLAY_BOTH])],
            'targetSurface' => ['required', Rule::in([BroadcastMessage::SURFACE_HOMEPAGE, BroadcastMessage::SURFACE_PLAYER_DASHBOARD, BroadcastMessage::SURFACE_BOTH])],
            'badgeLabel' => ['nullable', 'string', 'max:60'],
            'mediaType' => ['required', Rule::in([BroadcastMessage::MEDIA_NONE, BroadcastMessage::MEDIA_IMAGE, BroadcastMessage::MEDIA_VIDEO])],
            'mediaUrl' => ['nullable', 'string', 'max:1000'],
            'mediaFile' => $mediaFileRule,
            'isVideoOnly' => ['boolean'],
            'ctaLabel' => ['nullable', 'string', 'max:80'],
            'ctaUrl' => ['nullable', 'string', 'max:1000'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
        ]);

        $resolvedMedia = null;
        if ($this->displayType !== BroadcastMessage::DISPLAY_FEED && $this->mediaType !== BroadcastMessage::MEDIA_NONE) {
            if ($this->mediaFile instanceof TemporaryUploadedFile) {
                $resolvedMedia = $this->mediaFile->store('announcements', 'public');
            } else {
                $rawMediaUrl = trim($this->mediaUrl);
                if ($rawMediaUrl === '') {
                    $this->addError('mediaUrl', __('Provide a media URL or upload a file for the selected media type.'));

                    return;
                }
                if (! str_starts_with($rawMediaUrl, 'announcements/') && BroadcastMessage::sanitizeUrl($rawMediaUrl) === null) {
                    $this->addError('mediaUrl', __('Enter a valid http(s) or relative path URL.'));

                    return;
                }
                $resolvedMedia = $rawMediaUrl;
            }
        }

        $resolvedCtaUrl = null;
        if (trim($this->ctaUrl) !== '') {
            $resolvedCtaUrl = BroadcastMessage::sanitizeUrl($this->ctaUrl);
            if ($resolvedCtaUrl === null) {
                $this->addError('ctaUrl', __('Enter a valid http(s) or relative path URL (e.g. /tournaments/browse).'));

                return;
            }
        }

        $isPopupEnabled = $this->displayType !== BroadcastMessage::DISPLAY_FEED;

        $data = [
            'title' => $this->title,
            'message' => trim($this->message),
            'display_type' => $this->displayType,
            'target_surface' => $this->targetSurface,
            'badge_label' => $isPopupEnabled && trim($this->badgeLabel) !== '' ? trim($this->badgeLabel) : null,
            'media_type' => $isPopupEnabled ? $this->mediaType : BroadcastMessage::MEDIA_NONE,
            'media_url' => $isPopupEnabled ? $resolvedMedia : null,
            'cta_label' => $isPopupEnabled && trim($this->ctaLabel) !== '' ? trim($this->ctaLabel) : null,
            'cta_url' => $isPopupEnabled ? $resolvedCtaUrl : null,
            'is_video_only' => $mediaOnlyEnabled,
            'starts_at' => $this->startsAt ?: null,
            'ends_at' => $this->endsAt ?: null,
        ];

        if ($this->editingId) {
            BroadcastMessage::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Broadcast updated.');
        } else {
            BroadcastMessage::create(array_merge($data, ['uuid' => Str::uuid()->toString()]));
            session()->flash('success', 'Broadcast created.');
        }

        BroadcastMessage::clearCaches();
        $this->showFormModal = false;
        $this->resetForm();
        $this->dispatch('broadcast-saved');
    }

    public function confirmExpire(int $id): void
    {
        $this->confirmTargetId = $id;
        $this->confirmAction = 'expire';
        $this->showConfirmModal = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->guardSuperAdmin();

        $this->confirmTargetId = $id;
        $this->confirmAction = 'delete';
        $this->showConfirmModal = true;
    }

    public function executeConfirm(): void
    {
        if (! $this->confirmTargetId) {
            return;
        }

        $broadcast = BroadcastMessage::findOrFail($this->confirmTargetId);

        if ($this->confirmAction === 'expire') {
            $broadcast->update(['ends_at' => now()]);
            BroadcastMessage::clearCaches();
            session()->flash('success', 'Broadcast expired.');
        } elseif ($this->confirmAction === 'delete') {
            $this->guardSuperAdmin();
            app(AdminDeletionService::class)->delete('broadcasts', [$broadcast->id], $this->actor());
            BroadcastMessage::clearCaches();
            session()->flash('success', 'Broadcast deleted.');
        }

        $this->showConfirmModal = false;
        $this->confirmTargetId = null;
        $this->confirmAction = '';
    }

    public function cancelConfirm(): void
    {
        $this->showConfirmModal = false;
        $this->confirmTargetId = null;
        $this->confirmAction = '';
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->actor()->hasRole('SUPER_ADMIN');
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->title = '';
        $this->message = '';
        $this->displayType = BroadcastMessage::DISPLAY_FEED;
        $this->targetSurface = BroadcastMessage::SURFACE_BOTH;
        $this->badgeLabel = '';
        $this->mediaType = BroadcastMessage::MEDIA_NONE;
        $this->mediaUrl = '';
        $this->mediaFile = null;
        $this->isVideoOnly = false;
        $this->ctaLabel = '';
        $this->ctaUrl = '';
        $this->startsAt = '';
        $this->endsAt = '';
    }

    /**
     * Abort 403 if actor is not SUPER_ADMIN.
     */
    private function guardSuperAdmin(): void
    {
        if (! $this->isSuperAdmin()) {
            abort(403, 'Only Super Admins can perform this action.');
        }
    }

    // ── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        $query = BroadcastMessage::query()->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where(function ($q): void {
                $q->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('message', 'like', '%'.$this->search.'%');
            });
        }

        return view('livewire.admin.broadcast-notification-admin', [
            'broadcasts' => $query->paginate(15),
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Broadcast Notifications',
        ]);
    }
}
