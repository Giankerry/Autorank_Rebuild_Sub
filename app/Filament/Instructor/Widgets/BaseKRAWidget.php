<?php

namespace App\Filament\Instructor\Widgets;

use App\Models\Application;
use App\Models\PromotionCycle;
use App\Models\Submission;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select as FormSelect;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

abstract class BaseKRAWidget extends BaseWidget implements HasActions
{
    use InteractsWithActions;

    public $applicationOptions = [];
    public $selectedApplicationId = null;

    abstract protected function getKACategory(): string;

    abstract protected function getActiveSubmissionType(): string;

    #[On('applicationSelected')]
    public function syncApplicationId($applicationId): void
    {
        $this->selectedApplicationId = $applicationId;
        $this->resetTable();
    }

    public function mount(): void
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

        $this->selectedApplicationId = session('selected_app_id');
    }

    /**
     * Changed visibility from 'protected' to 'public' so the trait can access it.
     */
    abstract public function getGoogleDriveFolderPath(): array;

    public function createApplicationAction(): Action
    {
        return Action::make('createApplication')
            ->label('New Application')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->form([
                FormSelect::make('evaluation_cycle')
                    ->label('Promotion Cycle')
                    ->options(
                        PromotionCycle::where('is_active', true)->pluck('name', 'name')
                    )
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data) {
                $user = Auth::user();
                $newApplication = Application::create([
                    'user_id' => $user->id,
                    'applicant_current_rank' => $user->facultyRank?->name ?? 'N/A',
                    'status' => 'draft',
                    'evaluation_cycle' => $data['evaluation_cycle'],
                ]);

                $this->mount();

                session(['selected_app_id' => $newApplication->id]);
                $this->dispatch('applicationSelected', applicationId: $newApplication->id);
            });
    }

    protected function isMultipleSubmissionAllowed(): bool
    {
        return true;
    }

    protected function submissionExistsForCurrentType(): bool
    {
        if ($this->isMultipleSubmissionAllowed()) {
            return false;
        }

        $activeApplicationId = $this->selectedApplicationId;

        if (!$activeApplicationId) {
            return true;
        }

        return Submission::where('user_id', Auth::id())
            ->where('application_id', $activeApplicationId)
            ->where('type', $this->getActiveSubmissionType())
            ->exists();
    }

    protected function getCurrentSubmissionId(): ?int
    {
        $activeApplicationId = $this->selectedApplicationId;
        if (!$activeApplicationId) {
            return null;
        }

        return Submission::where('user_id', Auth::id())
            ->where('application_id', $activeApplicationId)
            ->where('type', $this->getActiveSubmissionType())
            ->value('id');
    }

    protected function getActionVisibility(): \Closure
    {
        return function (Submission $record): bool {
            if (is_null($record->application_id)) {
                return true;
            }
            return $record->application?->status === 'draft';
        };
    }

    public function getDisplayFormattingMap(): array
    {
        return [];
    }
}
