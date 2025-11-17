<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Application;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AdminStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalInstructors = User::role('Instructor')->count();
        $pendingActivation = User::role('Instructor')->whereNull('faculty_rank_id')->count();

        $appCounts = Application::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $pendingValidationCount = $appCounts['Pending Validation'] ?? 0;
        $draftCount = $appCounts['Draft'] ?? 0;
        $validatedCount = $appCounts['Validated'] ?? 0;
        $rejectedCount = $appCounts['Rejected'] ?? 0;

        return [
            Stat::make('Total Instructors', $totalInstructors)
                ->description('Total number of Instructors')
                ->icon('heroicon-o-users')
                ->color('gray'),

            Stat::make('Instructors Pending Activation', $pendingActivation)
                ->description('Instructors needing a faculty rank set')
                ->icon('heroicon-o-user-plus')
                ->color($pendingActivation > 0 ? 'warning' : 'success'),

            Stat::make('Applications Pending Validation', $pendingValidationCount)
                ->description('Applications waiting for validator review')
                ->icon('heroicon-o-inbox-stack')
                ->color($pendingValidationCount > 0 ? 'info' : 'gray'),

            Stat::make('Applications in Draft', $draftCount)
                ->description('Applications currently being built by instructors')
                ->icon('heroicon-o-pencil-square')
                ->color('gray'),
        ];
    }
}
