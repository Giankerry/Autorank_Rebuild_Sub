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

abstract class BaseKRAWidget extends BaseWidget implements HasActions
{
    use InteractsWithActions;

    public $applicationOptions = [];
    public $selectedApplicationId = null;

    /**
     * Define the KRA category for this widget.
     * e.g., 'KRA I', 'KRA II'
     */
    abstract protected function getKACategory(): string;

    /**
     * Get the submission type string based on the active table.
     */
    abstract protected function getActiveSubmissionType(): string;

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

        if (is_null($this->selectedApplicationId)) {
            $defaultApplication = $applications->where('status', 'draft')->first();
            $this->selectedApplicationId = $defaultApplication?->id;
        }
    }

    /**
     * Requires all child widgets to define their own nested
     * folder structure for Google Drive.
     *
     * @return array
     */
    abstract protected function getGoogleDriveFolderPath(): array;

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
                $this->selectedApplicationId = $newApplication->id;
                $this->resetTable();
            });
    }

    public function updatedSelectedApplicationId(): void
    {
        $this->resetTable();
    }

    /**
     * Controls whether this widget allows multiple submissions for a single type.
     * By default, it's true (multiple submissions allowed).
     * Override this in child widgets to *enforce* a single submission.
     */
    protected function isMultipleSubmissionAllowed(): bool
    {
        return true;
    }

    /**
     * Helper to check if a submission of the active type already exists.
     */
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

    /**
     * Helper to get the ID of the current submission if it exists.
     */
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

    /**
     * Helper to add the visibility logic to table actions.
     */
    protected function getActionVisibility(): \Closure
    {
        return function (Submission $record): bool {
            if (is_null($record->application_id)) {
                return true;
            }
            return $record->application?->status === 'draft';
        };
    }

    /**
     * Provides a map of formatting rules for the data.
     * Child widgets will override this.
     *
     * The key MUST be the *final display key* (e.g., "Material Type").
     * The value is either an array (for options) or a string (for date formats).
     *
     * @return array e.g., [
     * 'Material Type' => [
     * 'raw_key' => 'Display Value',
     * ],
     * 'Date Published' => 'm/d/Y',
     * ]
     */
    public function getDisplayFormattingMap(): array
    {
        return [];
    }
}
