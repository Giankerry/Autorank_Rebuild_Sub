<?php

namespace App\Filament\Instructor\Widgets\KRA1;

use App\Models\Submission;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Forms\Components\TrimmedIntegerInput;
use App\Tables\Columns\ScoreColumn;
use App\Filament\Instructor\Widgets\BaseKRAWidget;
use App\Filament\Traits\HandlesKRAFileUploads;
use App\Tables\Actions\ViewSubmissionFilesAction;

class MentorshipServicesWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a1.mentorship-services-widget';

    public ?string $activeTable = 'adviser';

    public array $availableMentorshipTypes = [];

    public function mount(): void
    {
        parent::mount();
        $this->loadAvailableTypes();
    }

    public function updatedActiveTable(): void
    {
        $this->resetTable();
        $this->loadAvailableTypes();
    }

    protected function getOptionsMaps(): array
    {
        return [
            'mentorship_type' => [
                'special_capstone_project' => 'Special/Capstone Project',
                'undergrad_thesis' => 'Undergrad Thesis',
                'masters_thesis' => 'Masters Thesis',
                'dissertation' => 'Dissertation',
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        return [
            'Mentorship Type' => $this->getOptionsMaps()['mentorship_type'],
            'Date Awarded' => 'm/d/Y',
        ];
    }

    private function loadAvailableTypes(): void
    {
        $allTypes = $this->getOptionsMaps()['mentorship_type'];

        $submittedTypes = Submission::where('user_id', Auth::id())
            ->where('application_id', $this->selectedApplicationId)
            ->where('type', $this->getActiveSubmissionType())
            ->pluck('data')
            ->pluck('mentorship_type')
            ->filter()
            ->all();

        $this->availableMentorshipTypes = array_filter(
            $allTypes,
            fn($key) => !in_array($key, $submittedTypes),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function getAvailableMentorshipTypes(): array
    {
        return $this->availableMentorshipTypes;
    }

    protected function getGoogleDriveFolderPath(): array
    {
        $kra = $this->getKACategory();

        switch ($this->activeTable) {
            case 'adviser':
                return [$kra, 'C: Mentorship Services', 'As Adviser'];
            case 'panel':
                return [$kra, 'C: Mentorship Services', 'As Panel'];
            case 'mentor':
                return [$kra, 'C: Mentorship Services', 'As Mentor (Competition)'];
            default:
                return [$kra, Str::slug($this->getActiveSubmissionType())];
        }
    }

    protected function getKACategory(): string
    {
        return 'KRA I';
    }

    protected function getActiveSubmissionType(): string
    {
        $typeMap = [
            'adviser' => 'mentorship-adviser',
            'panel' => 'mentorship-panel',
            'mentor' => 'mentorship-mentor',
        ];
        return $typeMap[$this->activeTable] ?? 'mentorship-adviser';
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

    private function getTableAcademicYearColumns(): array
    {
        $currentYear = (int) date('Y');
        $columns = [];

        for ($i = 0; $i < 4; $i++) {
            $startYear = $currentYear - ($i + 1);
            $endYear = $startYear + 1;
            $label = "AY {$startYear}–{$endYear}";
            $key = 'data.ay_' . (4 - $i) . '_count';

            $columns[] = Tables\Columns\TextColumn::make($key)->label($label);
        }

        return array_reverse($columns);
    }

    protected function getTableColumns(): array
    {
        return match ($this->activeTable) {
            'adviser', 'panel' => [
                Tables\Columns\TextColumn::make('data.mentorship_type')
                    ->label('Type')
                    ->formatStateUsing(fn(?string $state): string => $this->getOptionsMaps()['mentorship_type'][$state] ?? $state)
                    ->badge(),

                ...$this->getTableAcademicYearColumns(),

                ScoreColumn::make('score'),
            ],
            'mentor' => [
                Tables\Columns\TextColumn::make('data.competition_name')->label('Name of Competition')->wrap(),
                Tables\Columns\TextColumn::make('data.award_received')->label('Award Received'),
                Tables\Columns\TextColumn::make('data.date_awarded')->label('Date Awarded')->date('m/d/Y'),
                ScoreColumn::make('score'),
            ],
            default => [],
        };
    }

    protected function getTableHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add')
                ->form($this->getFormSchema())
                ->mutateFormDataUsing(function (array $data): array {
                    $data['user_id'] = Auth::id();
                    $data['application_id'] = $this->selectedApplicationId;
                    $data['category'] = $this->getKACategory();
                    $data['type'] = $this->getActiveSubmissionType();
                    return $data;
                })
                ->modalHeading(fn(): string => 'Submit New ' . Str::of($this->activeTable)->replace('_', ' ')->title())
                ->modalWidth('3xl')
                ->hidden(function (): bool {
                    if ($this->activeTable === 'mentor') {
                        return false;
                    }
                    return empty($this->availableMentorshipTypes);
                })
                ->after(function () {
                    $this->loadAvailableTypes();
                    $this->mount();
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewSubmissionFilesAction::make(),
            EditAction::make()
                ->form($this->getFormSchema())
                ->modalHeading(fn(): string => 'Edit ' . Str::of($this->activeTable)->replace('_', ' ')->title())
                ->modalWidth('3xl')
                ->visible($this->getActionVisibility()),
            DeleteAction::make()
                ->after(function () {
                    $this->loadAvailableTypes();
                    $this->mount();
                })
                ->visible($this->getActionVisibility()),
        ];
    }

    private function getAcademicYearFields(): array
    {
        $labelPrefix = $this->activeTable === 'adviser' ? 'No. of Student Advisees' : 'No. of Times as Panel';
        $currentYear = (int) date('Y');
        $fields = [];

        for ($i = 0; $i < 4; $i++) {
            $startYear = $currentYear - ($i + 1);
            $endYear = $startYear + 1;
            $label = "{$labelPrefix} (AY {$startYear}–{$endYear})";
            $key = 'ay_' . (4 - $i) . '_count';

            $fields[] = TrimmedIntegerInput::make('data.' . $key)
                ->label($label)
                ->required()
                ->default(0)
                ->minValue(0);
        }

        return array_reverse($fields);
    }

    protected function getFormSchema(): array
    {
        $schema = match ($this->activeTable) {
            'adviser', 'panel' => [
                Select::make('data.mentorship_type')
                    ->label('Mentorship Type')
                    ->options(function (?Submission $record): array {
                        $allTypes = $this->getOptionsMaps()['mentorship_type'];
                        if ($record) {
                            return $allTypes;
                        }
                        return $this->availableMentorshipTypes;
                    })
                    ->searchable()
                    ->required(),

                ...$this->getAcademicYearFields(),
            ],
            'mentor' => [
                TextInput::make('data.competition_name')->label('Name of the Academic Competition')->maxLength(150)->required()->columnSpanFull(),
                TextInput::make('data.sponsor')->label('Name of Sponsor Organization')->maxLength(150)->required(),
                TextInput::make('data.award_received')->label('Award Received')->maxLength(150)->required(),
                DatePicker::make('data.date_awarded')
                    ->label('Date Awarded')
                    ->native(false)
                    ->displayFormat('m/d/Y')
                    ->maxDate(now())
                    ->required(),
            ],
            default => [],
        };

        $schema[] = $this->getKRAFileUploadComponent();

        return $schema;
    }
}
