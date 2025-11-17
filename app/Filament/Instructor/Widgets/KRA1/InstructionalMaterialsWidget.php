<?php

namespace App\Filament\Instructor\Widgets\KRA1;

use App\Models\Application;
use App\Models\Submission;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Filament\Forms\Get;
use App\Forms\Components\TrimmedIntegerInput;
use App\Tables\Columns\ScoreColumn;
use App\Filament\Instructor\Widgets\BaseKRAWidget;
use App\Filament\Traits\HandlesKRAFileUploads;
use App\Tables\Actions\ViewSubmissionFilesAction;

class InstructionalMaterialsWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a1.instructional-materials-widget';

    public ?string $activeTable = 'sole_authorship';

    public function updatedActiveTable(): void
    {
        $this->resetTable();
    }

    public function getGoogleDriveFolderPath(): array
    {
        $kra = $this->getKACategory();

        switch ($this->activeTable) {
            case 'sole_authorship':
                return [$kra, 'B: Instructional Materials', 'Sole Authorship'];
            case 'co_authorship':
                return [$kra, 'B: Instructional Materials', 'Co-Authorship'];
            case 'academic_program':
                return [$kra, 'B: Academic Program Development'];
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
            'sole_authorship' => 'im-sole-authorship',
            'co_authorship' => 'im-co-authorship',
            'academic_program' => 'im-academic-program',
        ];
        return $typeMap[$this->activeTable] ?? 'im-sole-authorship';
    }

    protected function getOptionsMaps(): array
    {
        $currentYear = (int) date('Y');
        $academicYears = [];
        for ($i = 0; $i < 4; $i++) {
            $startYear = $currentYear - $i;
            $endYear = $startYear + 1;
            $academicYears["{$startYear}-{$endYear}"] = "{$startYear}-{$endYear}";
        }

        return [
            'material_type' => [
                'textbook' => 'Textbook',
                'textbook_chapter' => 'Textbook Chapter',
                'manual_module' => 'Manual/Module/Workbook',
                'multimedia_material' => 'Multimedia Teaching Material',
                'testing_material' => 'Testing Material',
            ],
            'program_type' => [
                'New Program' => 'New Program',
                'Revised Program' => 'Revised Program',
            ],
            'academic_year_implemented' => $academicYears,
            'role' => [
                'Lead' => 'Lead',
                'Contributor' => 'Contributor',
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        $maps = $this->getOptionsMaps();

        return [
            'Material Type' => $maps['material_type'],
            'Program Type' => $maps['program_type'],
            'Academic Year Implemented' => $maps['academic_year_implemented'],
            'Role' => $maps['role'],

            'Date Published' => 'm/d/Y',
            'Date Approved' => 'm/d/Y',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading(fn(): ?string => $this->getTableHeading())
            ->columns($this->getTableColumns())
            ->headerActions($this->getTableHeaderActions())
            ->actions($this->getTableActions())
            ->paginated(!$this->validation_mode)
            ->emptyStateHeading($this->getTableEmptyStateHeading())
            ->emptyStateDescription($this->getTableEmptyStateDescription());
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

    protected function getTableHeading(): ?string
    {
        return Str::of($this->activeTable)
            ->replace('_', ' ')
            ->title() . ' Submissions';
    }

    protected function getTableColumns(): array
    {
        $maps = $this->getOptionsMaps();

        return match ($this->activeTable) {
            'sole_authorship' => [
                Tables\Columns\TextColumn::make('data.title')->label('Title')->wrap(),
                Tables\Columns\TextColumn::make('data.material_type')
                    ->label('Material Type')
                    ->formatStateUsing(fn(?string $state) => $maps['material_type'][$state] ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('data.date_published')->label('Date Published')->date('m/d/Y'),
                Tables\Columns\TextColumn::make('data.date_approved')->label('Date Approved')->date('m/d/Y'),
                ScoreColumn::make('score'),
            ],
            'co_authorship' => [
                Tables\Columns\TextColumn::make('data.title')->label('Title')->wrap(),
                Tables\Columns\TextColumn::make('data.material_type')
                    ->label('Material Type')
                    ->formatStateUsing(fn(?string $state) => $maps['material_type'][$state] ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('data.date_published')->label('Date Published')->date('m/d/Y'),
                Tables\Columns\TextColumn::make('data.date_approved')->label('Date Approved')->date('m/d/Y'),
                Tables\Columns\TextColumn::make('data.contribution_percentage')->label('% Contribution')->suffix('%'),
                ScoreColumn::make('score'),
            ],
            'academic_program' => [
                Tables\Columns\TextColumn::make('data.program_name')->label('Name of Program')->wrap(),
                Tables\Columns\TextColumn::make('data.program_type')
                    ->label('Type of Program')
                    ->formatStateUsing(fn(?string $state) => $maps['program_type'][$state] ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('data.role')
                    ->label('Role')
                    ->formatStateUsing(fn(?string $state) => $maps['role'][$state] ?? $state),
                Tables\Columns\TextColumn::make('data.academic_year_implemented')->label('AY Implemented'),
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
                ->modalHeading(fn() => 'Submit New ' . Str::of($this->activeTable)->replace('_', ' ')->title())
                ->modalWidth('3xl')
                ->hidden($this->validation_mode)
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
            ViewSubmissionFilesAction::make(),
            EditAction::make()
                ->form($this->getFormSchema())
                ->modalHeading(fn() => 'Edit ' . Str::of($this->activeTable)->replace('_', ' ')->title())
                ->modalWidth('3xl')
                ->visible($this->getActionVisibility()),
            DeleteAction::make()
                ->after(fn() => $this->mount())
                ->visible($this->getActionVisibility()),
        ];
    }

    protected function getFormSchema(): array
    {
        $schema = [];
        $optionsMaps = $this->getOptionsMaps();

        switch ($this->activeTable) {
            case 'sole_authorship':
            case 'co_authorship':
                $schema = [
                    Textarea::make('data.title')
                        ->label('Title of Instructional Material')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('data.material_type')
                        ->label('Type of Instructional Material')
                        ->options($optionsMaps['material_type'])
                        ->searchable()
                        ->required(),
                    TextInput::make('data.reviewer')->label('Reviewer or Its Equivalent')->maxLength(150)->required(),
                    TextInput::make('data.publisher')->label('Publisher/Repository')->maxLength(150)->required(),
                    DatePicker::make('data.date_published')
                        ->label('Date Published')
                        ->native(false)
                        ->displayFormat('m/d/Y')
                        ->maxDate(now())
                        ->live()
                        ->required(),
                    DatePicker::make('data.date_approved')
                        ->label('Date Approved for Use')
                        ->native(false)
                        ->displayFormat('m/d/Y')
                        ->maxDate(now())
                        ->minDate(fn(Get $get) => $get('data.date_published'))
                        ->required(),
                ];

                if ($this->activeTable === 'co_authorship') {
                    $schema[] = TrimmedIntegerInput::make('data.contribution_percentage')
                        ->label('% Contribution')
                        ->minValue(1)
                        ->maxValue(100)
                        ->required();
                }
                break;

            case 'academic_program':
                $schema = [
                    TextInput::make('data.program_name')
                        ->label('Name of Academic Degree Program (provide complete name)')
                        ->maxLength(150)
                        ->required()
                        ->columnSpanFull(),
                    Select::make('data.program_type')
                        ->label('Type of Program')
                        ->options($optionsMaps['program_type'])
                        ->searchable()
                        ->required(),
                    TextInput::make('data.board_approval')
                        ->label('Board Approval (Board Resolution No.)')
                        ->maxLength(150)
                        ->required(),
                    Select::make('data.academic_year_implemented')
                        ->label('Academic Year Implemented')
                        ->options($optionsMaps['academic_year_implemented'])
                        ->rule('in:' . implode(',', array_keys($optionsMaps['academic_year_implemented'])))
                        ->required(),
                    Select::make('data.role')
                        ->label('Role')
                        ->options($optionsMaps['role'])
                        ->searchable()
                        ->required(),
                ];
                break;
        }

        $schema[] = $this->getKRAFileUploadComponent();

        return $schema;
    }
}
