<?php

namespace App\Filament\Instructor\Widgets\KRA3;

use App\Models\Application;
use App\Models\Submission;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Table;
use App\Filament\Instructor\Widgets\BaseKRAWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Forms\Components\TrimmedIntegerInput;
use App\Forms\Components\TrimmedNumericInput;
use App\Tables\Columns\ScoreColumn;
use App\Filament\Traits\HandlesKRAFileUploads;
use App\Tables\Actions\ViewSubmissionFilesAction;

class QualityOfExtensionWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a3.quality-of-extension-widget';

    public function getGoogleDriveFolderPath(): array
    {
        return [$this->getKACategory(), 'C: Quality of Extension Services'];
    }

    protected function isMultipleSubmissionAllowed(): bool
    {
        return false;
    }

    protected function getKACategory(): string
    {
        return 'KRA III';
    }

    protected function getActiveSubmissionType(): string
    {
        return 'extension-quality-rating';
    }

    protected function getOptionsMaps(): array
    {
        return [
            'deduction_reason' => [
                'NOT APPLICABLE' => 'Not Applicable',
                'ON APPROVED STUDY LEAVE' => 'On Approved Study Leave',
                'ON APPROVED SABBATICAL LEAVE' => 'On Approved Sabbatical Leave',
                'ON APPROVED MATERNITY LEAVE' => 'On Approved Maternity Leave',
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        return [
            'Client Deduction Reason' => $this->getOptionsMaps()['deduction_reason'],
        ];
    }

    public function table(Table $table): Table
    {
        $table = $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading('Client Satisfaction Rating Submission')
            ->columns([
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M j, Y g:ia')
                    ->sortable(),

                Tables\Columns\TextColumn::make('raw_score')
                    ->label('Overall Average Rating')
                    ->numeric(2, '.', ','),

                ScoreColumn::make('score'),
            ])
            ->headerActions($this->getTableHeaderActions())
            ->actions($this->getTableActions())
            ->paginated(!$this->validation_mode)
            ->emptyStateHeading($this->getTableEmptyStateHeading())
            ->emptyStateDescription($this->getTableEmptyStateDescription());

        if (!$this->validation_mode) {
            $table->checkIfRecordIsSelectableUsing(
                fn(Submission $record): bool => !$this->submissionExistsForCurrentType() || $record->id === $this->getCurrentSubmissionId()
            );
        }

        return $table;
    }

    protected function getTableQuery(): Builder
    {
        if ($this->validation_mode) {
            return Submission::query()
                ->where('application_id', $this->record->id)
                ->where('type', $this->getActiveSubmissionType());
        }

        return Submission::query()
            ->where('user_id', Auth::id())
            ->where('type', $this->getActiveSubmissionType())
            ->where('application_id', $this->selectedApplicationId);
    }

    private function getRatingFields(): array
    {
        $prefix = 'client';
        $fields = [];
        $currentYear = (int) date('Y');

        for ($yearIndex = 0; $yearIndex < 4; $yearIndex++) {
            $startYear = $currentYear - ($yearIndex + 1);
            $endYear = $startYear + 1;
            $ayLabel = "AY {$startYear}-{$endYear}";
            $yearKeySuffix = 4 - $yearIndex;

            $fields[] = Section::make($ayLabel)
                ->schema([
                    TrimmedNumericInput::make("data.{$prefix}_ay{$yearKeySuffix}_sem1")
                        ->label('1st Semester Rating')
                        ->step('0.01')
                        ->rules(['numeric', 'between:0,100'])
                        ->minValue(0)
                        ->maxValue(100)
                        ->required(),
                    TrimmedNumericInput::make("data.{$prefix}_ay{$yearKeySuffix}_sem2")
                        ->label('2nd Semester Rating')
                        ->step('0.01')
                        ->rules(['numeric', 'between:0,100'])
                        ->minValue(0)
                        ->maxValue(100)
                        ->required(),
                ])->columns(2);
        }
        return array_reverse($fields);
    }


    protected function getFormSchema(): array
    {
        $prefix = 'client';
        $deductedSemestersKey = "data.{$prefix}_deducted_semesters";
        $reasonKey = "data.{$prefix}_deduction_reason";

        return [
            Section::make('Client Satisfaction Ratings')
                ->description('Enter the average client satisfaction rating received per semester for the last 4 academic years. Rating scale: 0-100.')
                ->schema($this->getRatingFields())
                ->columns(2),

            Section::make('Leave / Deduction Information (If Applicable)')
                ->schema([
                    Select::make($reasonKey)
                        ->label('Reason for Deducting Semesters (Leave)')
                        ->options($this->getOptionsMaps()['deduction_reason'])
                        ->default('NOT APPLICABLE')
                        ->searchable()
                        ->required()
                        ->live(),

                    TrimmedIntegerInput::make($deductedSemestersKey)
                        ->label('Number of Semesters to Deduct')
                        ->minValue(0)
                        ->maxValue(7)
                        ->default(0)
                        ->required()
                        ->visible(fn(Get $get): bool => $get($reasonKey) !== 'NOT APPLICABLE'),
                ])->columns(2),

            $this->getKRAFileUploadComponent(),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add')
                ->form($this->getFormSchema())
                ->disabled(function () {
                    $application = Application::find($this->selectedApplicationId);
                    if (!$application) {
                        return true;
                    }
                    return $application->status !== 'Draft';
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['user_id'] = Auth::id();
                    $data['application_id'] = $this->selectedApplicationId;
                    $data['category'] = $this->getKACategory();
                    $data['type'] = $this->getActiveSubmissionType();
                    return $data;
                })
                ->modalHeading('Submit Client Satisfaction Ratings')
                ->modalWidth('4xl')
                ->hidden(fn(): bool => $this->submissionExistsForCurrentType() || $this->validation_mode)
                ->after(fn() => $this->mount()),
        ];
    }

    protected function getTableActions(): array
    {
        if ($this->validation_mode) {
            return [
                $this->getViewFilesAction(),
                $this->getValidateSubmissionAction(),
            ];
        }

        return [
            $this->getViewFilesAction(),

            EditAction::make()
                ->label('Edit Rating Data')
                ->form($this->getFormSchema())
                ->modalHeading('Edit Client Satisfaction Ratings')
                ->modalWidth('4xl')
                ->visible($this->getActionVisibility()),
            DeleteAction::make()
                ->after(fn() => $this->mount())
                ->visible($this->getActionVisibility()),
        ];
    }
}
