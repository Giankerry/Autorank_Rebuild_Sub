<?php

namespace App\Filament\Instructor\Widgets\KRA1;

use App\Models\Application;
use App\Models\Submission;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Forms\Components\TrimmedIntegerInput;
use App\Forms\Components\TrimmedNumericInput;
use App\Tables\Columns\ScoreColumn;
use App\Filament\Instructor\Widgets\BaseKRAWidget;
use App\Filament\Traits\HandlesKRAFileUploads;
use App\Tables\Actions\ViewSubmissionFilesAction;

class TeachingEffectivenessWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a1.teaching-effectiveness-widget';

    public ?string $activeTable = 'student_evaluation';

    public function updatedActiveTable(): void
    {
        $this->resetTable();
    }

    public function getGoogleDriveFolderPath(): array
    {
        $kra = $this->getKACategory();

        switch ($this->activeTable) {
            case 'student_evaluation':
                return [$kra, 'A: Teaching Effectiveness', 'Student Evaluation'];
            case 'supervisor_evaluation':
                return [$kra, 'A: Teaching Effectiveness', 'Supervisor Evaluation'];
            default:
                return [$kra, Str::slug($this->getActiveSubmissionType())];
        }
    }

    protected function isMultipleSubmissionAllowed(): bool
    {
        return false;
    }

    protected function getKACategory(): string
    {
        return 'KRA I';
    }

    protected function getActiveSubmissionType(): string
    {
        return $this->activeTable === 'student_evaluation'
            ? 'te-student-evaluation'
            : 'te-supervisor-evaluation';
    }

    protected function getOptionsMaps(): array
    {
        return [
            'reason_for_deducting' => [
                'NOT APPLICABLE' => 'Not Applicable',
                'ON APPROVED STUDY LEAVE' => 'On Approved Study Leave',
                'ON APPROVED SABBATICAL LEAVE' => 'On Approved Sabbatical Leave',
                'ON APPROVED MATERNITY LEAVE' => 'On Approved Maternity Leave',
            ]
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        return [
            'Reason For Deducting' => $this->getOptionsMaps()['reason_for_deducting'],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading(fn(): string => $this->getTableHeading())
            ->columns($this->getTableColumns())
            ->headerActions($this->getTableHeaderActions())
            ->actions($this->getTableActions())
            ->checkIfRecordIsSelectableUsing(
                fn(Submission $record): bool => !$this->submissionExistsForCurrentType() || $record->id === $this->getCurrentSubmissionId()
            );
    }

    protected function getTableQuery(): Builder
    {
        return Submission::query()
            ->where('user_id', Auth::id())
            ->where('type', $this->getActiveSubmissionType())
            ->where('application_id', $this->selectedApplicationId);
    }

    protected function getTableHeading(): string
    {
        return Str::of($this->activeTable)->replace('_', ' ')->title() . ' Submissions';
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('updated_at')
                ->label('Last Updated')
                ->dateTime('M j, Y g:ia')
                ->sortable(),

            Tables\Columns\TextColumn::make('raw_score')
                ->label('Overall Average Rating')
                ->badge()
                ->numeric(2, '.', ','),

            ScoreColumn::make('score'),
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
                    return $application->status !== 'draft';
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['user_id'] = Auth::id();
                    $data['application_id'] = $this->selectedApplicationId;
                    $data['category'] = $this->getKACategory();
                    $data['type'] = $this->getActiveSubmissionType();
                    return $data;
                })
                ->modalHeading('Submit ' . Str::of($this->activeTable)->replace('_', ' ')->title())
                ->modalWidth('4xl')
                ->hidden(fn(): bool => $this->submissionExistsForCurrentType())
                ->after(fn() => $this->mount()),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewSubmissionFilesAction::make(),
            EditAction::make()
                ->label('Edit Evaluation Data')
                ->form($this->getFormSchema())
                ->modalHeading('Edit ' . Str::of($this->activeTable)->replace('_', ' ')->title())
                ->modalWidth('4xl')
                ->visible($this->getActionVisibility()),
            DeleteAction::make()
                ->after(fn() => $this->mount())
                ->visible($this->getActionVisibility()),
        ];
    }

    private function getRatingFields(): array
    {
        $prefix = $this->activeTable === 'student_evaluation' ? 'student' : 'supervisor';
        $fields = [];
        $currentYear = (int) date('Y');

        for ($yearIndex = 0; $yearIndex < 4; $yearIndex++) {
            $startYear = $currentYear - ($yearIndex + 1);
            $endYear = $startYear + 1;
            $ayLabel = "AY {$startYear}–{$endYear}";
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
        $prefix = $this->activeTable === 'student_evaluation' ? 'student' : 'supervisor';
        $deductedSemestersKey = "data.{$prefix}_deducted_semesters";

        return [
            Section::make('Evaluation Ratings')
                ->description('Enter the average rating received per semester for the last 4 academic years.')
                ->schema($this->getRatingFields())
                ->columns(2),

            Section::make('Leave / Deduction Information (If Applicable)')
                ->schema([
                    Select::make('data.reason_for_deducting')
                        ->label('Reason for Deducting Semesters (Leave)')
                        ->options($this->getOptionsMaps()['reason_for_deducting'])
                        ->default('NOT APPLICABLE')
                        ->required()
                        ->live(),

                    TrimmedIntegerInput::make($deductedSemestersKey)
                        ->label('Number of Semesters to Deduct')
                        ->minValue(0)
                        ->maxValue(7)
                        ->default(0)
                        ->required()
                        ->visible(fn(Get $get): bool => $get('data.reason_for_deducting') !== 'NOT APPLICABLE'),
                ])->columns(2),

            $this->getKRAFileUploadComponent(),
        ];
    }
}
