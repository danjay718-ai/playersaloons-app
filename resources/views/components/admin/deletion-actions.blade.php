@props(['resource', 'parentId' => null])

@can(app(\App\Modules\Operations\Services\AdminDeletionService::class)->definition($resource)[1])
    <div class="col-span-full mt-4 flex items-center justify-end gap-1">
        <span class="text-xs font-medium text-slate-400">Actions</span>
        <x-admin.action-dropdown>
            <div class="py-1">
                <livewire:admin.recoverable-delete :resource="$resource" :parent-id="$parentId" :menu-item="true" :key="'bulk-delete-'.$resource.'-'.$parentId" />
            </div>
        </x-admin.action-dropdown>
    </div>
@endcan
