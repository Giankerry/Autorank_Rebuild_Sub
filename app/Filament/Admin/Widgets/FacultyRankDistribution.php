<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Traits\ManagesPanelColors;
use App\Models\FacultyRank;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class FacultyRankDistribution extends ChartWidget
{
    use ManagesPanelColors;

    protected static ?string $heading = 'Faculty Rank Distribution';

    protected static ?int $sort = 2;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }

    protected function getData(): array
    {
        $unassignedCount = User::role('Instructor')
            ->whereNull('faculty_rank_id')
            ->count();

        $ranks = FacultyRank::withCount(['users' => function ($query) {
            $query->role('Instructor');
        }])
            ->orderBy('level')
            ->get();

        $labels = $ranks->pluck('name')->toArray();
        $data = $ranks->pluck('users_count')->toArray();

        array_unshift($labels, 'Unassigned');
        array_unshift($data, $unassignedCount);

        $panelColors = $this->getPanelColors();
        $primaryShades = $panelColors['primary'];

        $rgb600 = $primaryShades[600];
        $rgb700 = $primaryShades[700];

        $barColor = "rgba({$rgb600}, 0.7)";
        $borderColor = "rgb({$rgb700})";

        return [
            'datasets' => [
                [
                    'label' => 'Total Instructors',
                    'data' => $data,
                    'backgroundColor' => $barColor,
                    'borderColor' => $borderColor,
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
