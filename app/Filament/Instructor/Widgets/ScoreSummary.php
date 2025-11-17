<?php

namespace App\Filament\Instructor\Widgets;

use App\Models\Application;
use App\Models\Submission;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class ScoreSummary extends BaseWidget
{
    protected ?string $heading = 'Promotion Application Summary';

    protected static string $view = 'filament.instructor.widgets.score-summary-header';

    public $applicationOptions = [];
    public $selectedApplicationId = null;

    #[On('applicationSelected')]
    public function syncApplicationId($applicationId): void
    {
        $this->refreshApplicationOptions();
        $this->selectedApplicationId = $applicationId;
    }

    public function mount(): void
    {
        $this->refreshApplicationOptions();

        $this->selectedApplicationId = session('selected_app_id');

        if (is_null($this->selectedApplicationId)) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $this->selectedApplicationId = $user->applications()->latest()->first()?->id;
        }

        session(['selected_app_id' => $this->selectedApplicationId]);
        $this->dispatch('applicationSelected', applicationId: $this->selectedApplicationId);
    }

    public function updatedSelectedApplicationId(): void
    {
        session(['selected_app_id' => $this->selectedApplicationId]);
        $this->dispatch('applicationSelected', applicationId: $this->selectedApplicationId);
    }

    protected function getStats(): array
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $selectedApplication = null;
        if ($this->selectedApplicationId) {
            $selectedApplication = $user->applications()->find($this->selectedApplicationId);
        }

        if (!$this->selectedApplicationId) {
            $unassignedCount = Submission::where('user_id', $user->id)
                ->whereNull('application_id')
                ->count();

            return [
                Stat::make('Viewing Unassigned Submissions', $unassignedCount . ' items')
                    ->description('These items are not part of an application.')
                    ->icon('heroicon-o-folder-minus')
                    ->color('gray'),
            ];
        }

        if (!$selectedApplication) {
            return [
                Stat::make('No Application Selected', 'N/A')
                    ->description('Select an application from the filter above.')
                    ->icon('heroicon-o-document-minus')
                    ->color('gray'),
            ];
        }

        if ($selectedApplication->final_score === null) {
            return [
                Stat::make('No Calculated Score', 'N/A')
                    ->description('Submissions for this application are not yet finalized.')
                    ->descriptionIcon('heroicon-o-information-circle')
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('gray'),
            ];
        }

        $finalScore = number_format($selectedApplication->final_score, 2);
        $kra1Score = number_format($selectedApplication->kra1_score ?? 0, 2);
        $kra2Score = number_format($selectedApplication->kra2_score ?? 0, 2);
        $kra3Score = number_format($selectedApplication->kra3_score ?? 0, 2);
        $kra4Score = number_format($selectedApplication->kra4_score ?? 0, 2);
        $currentRank = $selectedApplication->applicant_current_rank ?? 'N/A';
        $attainableRank = $selectedApplication->highest_attainable_rank ?? 'N/A';

        $rankColor = 'success';
        $rankDescription = 'Highest rank achieved based on score';
        if ($attainableRank === $currentRank) {
            $rankColor = 'warning';
            $rankDescription = 'Score matches current rank (No Promotion)';
        } elseif ($attainableRank === 'Rank Not Found' || $attainableRank === 'Weights Not Found') {
            $rankColor = 'danger';
            $rankDescription = 'Error in Rank Determination (Check Base Rank/Weights)';
        }

        return [
            Stat::make('Current Base Rank', $currentRank)
                ->description('Rank used for weighted scoring')
                ->icon('heroicon-o-user-circle')
                ->color('primary'),
            Stat::make('Final Total Score', $finalScore)
                ->description('Weighted sum across all KRAs (Max 100)')
                ->icon('heroicon-o-star')
                ->color('primary'),
            Stat::make('Attainable Rank', $attainableRank)
                ->description($rankDescription)
                ->icon('heroicon-o-academic-cap')
                ->color($rankColor),
            Stat::make('KRA I: Instruction', $kra1Score)->color('info'),
            Stat::make('KRA II: Research & Creative Works', $kra2Score)->color('info'),
            Stat::make('KRA III: Extension Services', $kra3Score)->color('info'),
            Stat::make('KRA IV: Professional Development', $kra4Score)->color('info'),
        ];
    }

    private function refreshApplicationOptions(): void
    {
        $user = Auth::user();

        $applications = Application::where('user_id', $user->id)
            ->withCount('submissions')
            ->orderBy('created_at', 'desc')
            ->get();

        $unassignedCount = Submission::where('user_id', $user->id)
            ->whereNull('application_id')
            ->count();

        $this->applicationOptions = [null => "Unassigned ({$unassignedCount} items)"];
        foreach ($applications as $app) {
            $item_label = $app->submissions_count === 1 ? 'item' : 'items';
            $this->applicationOptions[$app->id] = "{$app->evaluation_cycle} ({$app->status}) - {$app->submissions_count} {$item_label}";
        }
    }

    protected function isPolling(): bool
    {
        return false;
    }
}
