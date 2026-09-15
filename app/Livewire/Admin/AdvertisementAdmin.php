<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Community\Models\Advertisement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AdvertisementAdmin extends AdminComponent
{
    public ?int $editingId = null;

    public string $title = '';

    public string $description = '';

    public string $imageUrl = '';

    public string $targetUrl = '';

    public string $ctaLabel = 'Learn more';

    public string $startsAt = '';

    public string $endsAt = '';

    public bool $isActive = false;

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('advertisements.view'), 403);
    }

    public function edit(int $id): void
    {
        $ad = Advertisement::query()->findOrFail($id);
        $this->editingId = $ad->id;
        $this->title = $ad->title;
        $this->description = (string) $ad->description;
        $this->imageUrl = (string) $ad->image_url;
        $this->targetUrl = (string) $ad->target_url;
        $this->ctaLabel = $ad->cta_label;
        $this->startsAt = $ad->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->endsAt = $ad->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->isActive = $ad->is_active;
    }

    public function save(): void
    {
        abort_unless($this->actor()->can('advertisements.manage'), 403);
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:1000'],
            'imageUrl' => ['nullable', 'url', 'max:2048'], 'targetUrl' => ['nullable', 'url', 'max:2048'], 'ctaLabel' => ['required', 'string', 'max:50'],
            'startsAt' => ['nullable', 'date'], 'endsAt' => ['nullable', 'date', 'after:startsAt'], 'isActive' => ['boolean'],
        ]);
        Advertisement::query()->updateOrCreate(['id' => $this->editingId], [
            'uuid' => $this->editingId ? Advertisement::query()->findOrFail($this->editingId)->uuid : Str::uuid(),
            'title' => $data['title'], 'description' => $data['description'] ?: null, 'image_url' => $data['imageUrl'] ?: null,
            'target_url' => $data['targetUrl'] ?: null, 'cta_label' => $data['ctaLabel'], 'starts_at' => $data['startsAt'] ?: null,
            'ends_at' => $data['endsAt'] ?: null, 'is_active' => $data['isActive'], 'created_by' => Auth::id(),
        ]);
        $this->reset(['editingId', 'title', 'description', 'imageUrl', 'targetUrl', 'startsAt', 'endsAt', 'isActive']);
        $this->ctaLabel = 'Learn more';
        session()->flash('success', 'Advertisement saved.');
    }

    public function delete(int $id): void
    {
        abort_unless($this->actor()->can('advertisements.manage'), 403);
        app(\App\Modules\Operations\Services\AdminDeletionService::class)->delete('advertisements', [$id], $this->actor());
        if ($this->editingId === $id) {
            $this->reset(['editingId', 'title', 'description']);
        }
        session()->flash('success', 'Advertisement deleted.');
    }

    public function render()
    {
        return view('livewire.admin.advertisement-admin', ['advertisements' => Advertisement::query()->latest()->get()])
            ->layout('components.layouts.admin', ['admin_title' => 'Advertisements & Promotions']);
    }
}
