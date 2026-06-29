<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\CMS\Models\PolicyPage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PolicyAdmin extends AdminComponent
{
    public ?int $selectedPolicyId = null;

    public string $title = '';

    public string $slug = '';

    public string $summary = '';

    public string $content = '';

    public int $sortOrder = 0;

    public bool $isActive = true;

    public bool $isPublished = true;

    public function mount(): void
    {
        $firstPolicy = PolicyPage::query()->orderBy('sort_order')->first();

        if ($firstPolicy) {
            $this->selectPolicy((int) $firstPolicy->id);
        }
    }

    public function selectPolicy(int $policyId): void
    {
        $policy = PolicyPage::findOrFail($policyId);

        $this->selectedPolicyId = (int) $policy->id;
        $this->title = $policy->title;
        $this->slug = $policy->slug;
        $this->summary = (string) $policy->summary;
        $this->content = $policy->content;
        $this->sortOrder = (int) $policy->sort_order;
        $this->isActive = (bool) $policy->is_active;
        $this->isPublished = $policy->published_at !== null;

        $this->dispatch('policy-content-selected', content: $this->content);
    }

    public function savePolicy(): void
    {
        $this->content = trim($this->content);

        $this->validate([
            'selectedPolicyId' => 'required|integer|exists:policy_pages,id',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:150|alpha_dash|unique:policy_pages,slug,'.$this->selectedPolicyId,
            'summary' => 'nullable|string|max:255',
            'content' => 'required|string|min:20',
            'sortOrder' => 'integer|min:0|max:65535',
            'isActive' => 'boolean',
            'isPublished' => 'boolean',
        ]);

        PolicyPage::findOrFail($this->selectedPolicyId)->update([
            'title' => $this->title,
            'slug' => Str::slug($this->slug),
            'summary' => $this->summary !== '' ? $this->summary : null,
            'content' => $this->content,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'published_at' => $this->isPublished ? now() : null,
            'updated_by' => Auth::id(),
        ]);

        session()->flash('success', 'Policy page saved successfully.');
    }

    public function render()
    {
        $policies = PolicyPage::query()
            ->with('updater')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return view('livewire.admin.policy-admin', [
            'policies' => $policies,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Policy Pages',
        ]);
    }
}
