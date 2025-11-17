<?php

namespace App\Filament\Instructor\Widgets\KRA2;

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
use App\Filament\Instructor\Widgets\BaseKRAWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Forms\Components\TrimmedIntegerInput;
use App\Tables\Columns\ScoreColumn;
use App\Filament\Traits\HandlesKRAFileUploads;
use App\Tables\Actions\ViewSubmissionFilesAction;

class PatentedInventionsWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a2.patented-inventions-widget';

    public ?string $activeTable = 'invention_patent_sole';

    public function updatedActiveTable(): void
    {
        $this->resetTable();
    }

    public function getGoogleDriveFolderPath(): array
    {
        $kra = $this->getKACategory();
        $baseFolder = 'B: Patented Inventions';

        switch ($this->activeTable) {
            case 'invention_patent_sole':
                return [$kra, $baseFolder, 'Invention Patent - Sole'];
            case 'invention_patent_co':
                return [$kra, $baseFolder, 'Invention Patent - Co-Inventor'];
            case 'utility_design_sole':
                return [$kra, $baseFolder, 'Utility-Design - Sole'];
            case 'utility_design_co':
                return [$kra, $baseFolder, 'Utility-Design - Co-Inventor'];
            case 'commercialized_local':
                return [$kra, $baseFolder, 'Commercialized - Local'];
            case 'commercialized_intl':
                return [$kra, $baseFolder, 'Commercialized - International'];
            default:
                return [$kra, $baseFolder, Str::slug($this->activeTable)];
        }
    }

    protected function getKACategory(): string
    {
        return 'KRA II';
    }

    protected function getActiveSubmissionType(): string
    {
        return match ($this->activeTable) {
            'invention_patent_sole' => 'invention-patent-sole',
            'invention_patent_co' => 'invention-patent-co-inventor',
            'utility_design_sole' => 'invention-utility-design-sole',
            'utility_design_co' => 'invention-utility-design-co-inventor',
            'commercialized_local' => 'invention-commercialized-local',
            'commercialized_intl' => 'invention-commercialized-international',
            default => 'invention-patent-sole',
        };
    }

    protected function getOptionsMaps(): array
    {
        return [
            'patent_stage' => [
                'accepted' => 'Accepted',
                'published' => 'Published',
                'granted' => 'Granted'
            ],
            'patent_type' => [
                'utility_model' => 'Utility Model',
                'industrial_design' => 'Industrial Design'
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        $maps = $this->getOptionsMaps();

        return [
            'Patent Stage' => $maps['patent_stage'],
            'Patent Type' => $maps['patent_type'],
            'Application Date' => 'm/d/Y',
            'Date Granted' => 'm/d/Y',
            'Date Patented' => 'm/d/Y',
            'Date Commercialized' => 'm/d/Y',
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

    protected function getTableHeading(): string
    {
        return Str::of($this->activeTable)
            ->replace('_', ' ')
            ->replace(' co ', ' Co-Inventor ')
            ->replace(' intl', ' International')
            ->title() . ' Submissions';
    }

    protected function getTableColumns(): array
    {
        $columns = [];
        $maps = $this->getOptionsMaps();

        $columns[] = Tables\Columns\TextColumn::make('data.name')->label('Name')->wrap();

        switch ($this->activeTable) {
            case 'invention_patent_sole':
            case 'invention_patent_co':
                $columns[] = Tables\Columns\TextColumn::make('data.application_date')->label('Application Date')->date('m/d/Y');
                $columns[] = Tables\Columns\TextColumn::make('data.patent_stage')->label('Patent Stage')->formatStateUsing(fn(?string $state): string => $maps['patent_stage'][$state] ?? Str::title($state ?? ''))->badge();
                break;
            case 'utility_design_sole':
            case 'utility_design_co':
                $columns[] = Tables\Columns\TextColumn::make('data.patent_type')->label('Type')->formatStateUsing(fn(?string $state): string => $maps['patent_type'][$state] ?? Str::of($state)->replace('_', ' ')->title())->badge();
                $columns[] = Tables\Columns\TextColumn::make('data.application_date')->label('Application Date')->date('m/d/Y');
                $columns[] = Tables\Columns\TextColumn::make('data.date_granted')->label('Date Granted')->date('m/d/Y');
                break;
            case 'commercialized_local':
            case 'commercialized_intl':
                $columns[] = Tables\Columns\TextColumn::make('data.patent_type')->label('Type of Product');
                $columns[] = Tables\Columns\TextColumn::make('data.date_patented')->label('Date Patented')->date('m/d/Y');
                $columns[] = Tables\Columns\TextColumn::make('data.date_commercialized')->label('Date Commercialized')->date('m/d/Y');
                $columns[] = Tables\Columns\TextColumn::make('data.area_commercialized')->label('Area Commercialized');
                break;
        }

        if (in_array($this->activeTable, ['invention_patent_co', 'utility_design_co'])) {
            $columns[] = Tables\Columns\TextColumn::make('data.contribution_percentage')->label('% Contribution')->suffix('%');
        }

        $columns[] = ScoreColumn::make('score');

        return $columns;
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
                ->modalHeading(fn(): string => 'Submit New ' . Str::of($this->activeTable)->replace('_', ' ')->title())
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
                ->modalHeading(fn(): string => 'Edit ' . Str::of($this->activeTable)->replace('_', ' ')->title())
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
        $maps = $this->getOptionsMaps();

        switch ($this->activeTable) {
            case 'invention_patent_sole':
            case 'invention_patent_co':
                $schema = [
                    Textarea::make('data.name')->label('Name of the Invention')->maxLength(255)->required()->columnSpanFull(),
                    DatePicker::make('data.application_date')
                        ->label('Application Date')
                        ->native(false)
                        ->displayFormat('m/d/Y')
                        ->maxDate(now())
                        ->required(),
                    Select::make('data.patent_stage')
                        ->label('Patent Application Stage')
                        ->options($maps['patent_stage'])
                        ->searchable()
                        ->required(),
                    DatePicker::make('data.date_granted')
                        ->label('Date Accepted / Published / Granted')
                        ->native(false)
                        ->displayFormat('m/d/Y')
                        ->maxDate(now())
                        ->required(),
                ];
                break;
            case 'utility_design_sole':
            case 'utility_design_co':
                $schema = [
                    Textarea::make('data.name')->label('Name of Invention/Design')->maxLength(255)->required()->columnSpanFull(),
                    Select::make('data.patent_type')
                        ->label('Type of Patent')
                        ->options($maps['patent_type'])
                        ->searchable()
                        ->required(),
                    DatePicker::make('data.application_date')
                        ->label('Date of Application')
                        ->native(false)
                        ->displayFormat('m/d/Y')
                        ->maxDate(now())
                        ->required(),
                    DatePicker::make('data.date_granted')
                        ->label('Date Granted')
                        ->native(false)
                        ->displayFormat('m/d/Y')
                        ->maxDate(now())
                        ->required(),
                ];
                break;
            case 'commercialized_local':
            case 'commercialized_intl':
                $schema = [
                    Textarea::make('data.name')->label('Name of Patented Product')->maxLength(255)->required()->columnSpanFull(),
                    TextInput::make('data.patent_type')->label('Type of Product')->maxLength(100)->required(),
                    DatePicker::make('data.date_patented')
                        ->label('Date Patented')
                        ->native(false)
                        ->displayFormat('m/d/Y')
                        ->maxDate(now())
                        ->required(),
                    DatePicker::make('data.date_commercialized')
                        ->label('Date Product was First Commercialized')
                        ->native(false)
                        ->displayFormat('m/d/Y')
                        ->maxDate(now())
                        ->required(),
                    TextInput::make('data.area_commercialized')->label('Area/Place Commercialized')->maxLength(150)->required(),
                ];
                break;
        }

        if (in_array($this->activeTable, ['invention_patent_co', 'utility_design_co'])) {
            $schema[] = TrimmedIntegerInput::make('data.contribution_percentage')
                ->label('% Contribution')
                ->minValue(1)
                ->maxValue(100)
                ->required();
        }

        $schema[] = $this->getKRAFileUploadComponent();

        return $schema;
    }
}
