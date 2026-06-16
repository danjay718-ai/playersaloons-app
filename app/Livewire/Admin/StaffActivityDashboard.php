<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class StaffActivityDashboard extends AdminComponent
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public string $staffFilter = '';

    public function boot(): void
    {
        parent::boot();

        $user = Auth::user();
        if (! $user || ! $user->hasAnyRole(['SUPER_ADMIN', 'ADMIN'])) {
            abort(403, 'Only admins may view the staff activity dashboard.');
        }
    }

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo   = now()->format('Y-m-d');
    }

    public function render()
    {
        $staffRoles = ['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER', 'SUPPORT_AGENT', 'FINANCE_OPERATOR', 'KYC_REVIEWER'];

        $staffQuery = User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', $staffRoles));
        if ($this->staffFilter) {
            $staffQuery->where('username', 'like', '%'.$this->staffFilter.'%');
        }
        $staffUsers = $staffQuery->with('roles')->orderBy('username')->get();

        $from = $this->dateFrom ? $this->dateFrom.' 00:00:00' : now()->subDays(7)->startOfDay();
        $to   = $this->dateTo   ? $this->dateTo.' 23:59:59'   : now()->endOfDay();

        /** @var array<int, array{user: User, counts: array<string,int>, total: int, last_at: \Illuminate\Support\Carbon|null}> $rows */
        $rows = [];

        foreach ($staffUsers as $staff) {
            $logs = Activity::query()
                ->where('causer_type', User::class)
                ->where('causer_id', $staff->id)
                ->whereBetween('created_at', [$from, $to])
                ->get();

            $counts = $logs->groupBy('description')
                ->map(fn ($g) => $g->count())
                ->toArray();

            $rows[] = [
                'user'    => $staff,
                'counts'  => $counts,
                'total'   => $logs->count(),
                'last_at' => $logs->sortByDesc('created_at')->first()?->created_at,
            ];
        }

        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        $topActions = Activity::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('causer_id', $staffUsers->pluck('id'))
            ->where('causer_type', User::class)
            ->selectRaw('description, COUNT(*) as total')
            ->groupBy('description')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return view('livewire.admin.staff-activity-dashboard', [
            'rows'       => $rows,
            'topActions' => $topActions,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Staff Activity Dashboard',
        ]);
    }
}
