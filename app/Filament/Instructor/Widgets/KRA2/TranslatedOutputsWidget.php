<?php

namespace App\Filament\Instructor\Widgets\KRA2;

use App\Models\Application;
use App\Models\Submission;
use Filament\Forms\Components\DatePicker;
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

class TranslatedOutputsWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a2.translated-outputs-widget';

    public ?string $activeTable = 'lead_researcher';

    public function updatedActiveTable(): void
    {
        $this->resetTable();
    }

    public function getGoogleDriveFolderPath(): array
    {
        $kra = $this->getKACategory();

        switch ($this->activeTable) {
            case 'lead_researcher':
                return [$kra, 'B: Translated Outputs', 'Lead Researcher'];
            case 'contributor':
                return [$kra, 'B: Translated Outputs', 'Contributor'];
            default:
                return [$kra, Str::slug($this->getActiveSubmissionType())];
        }
    }

    protected function getKACategory(): string
    {
        return 'KRA II';
    }

    protected function getActiveSubmissionType(): string
    {
        return $this->activeTable === 'lead_researcher'
            ? 'research-translated-lead'
            : 'research-translated-contributor';
    }

    public function getDisplayFormattingMap(): array
    {
        return [
            'Date Completed' => 'm/d/Y',
            'Date Utilized' => 'm/d/Y',
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
        return Str::of($this->activeTable)->replace('_', ' ')->title() . ' Submissions';
    }

    protected function getTableColumns(): array
    {
        return match ($this->activeTable) {
            'lead_researcher' => [
                Tables\Columns\TextColumn::make('data.title')->label('Title of Research')->wrap(),
                Tables\Columns\TextColumn::make('data.project_name')->label('Project/Policy/Product Name')->wrap(),
                Tables\Columns\TextColumn::make('data.date_completed')->label('Date Completed')->date('m/d/Y'),
                Tables\Columns\TextColumn::make('data.date_utilized')->label('Date Utilized/Implemented')->date('m/d/Y'),
                ScoreColumn::make('score'),
            ],
            'contributor' => [
                Tables\Columns\TextColumn::make('data.title')->label('Title of Research')->wrap(),
                Tables\Columns\TextColumn::make('data.project_name')->label('Project/Policy/Product Name')->wrap(),
                Tables\Columns\TextColumn::make('data.date_completed')->label('Date Completed')->date('m/d/Y'),
                Tables\Columns\TextColumn::make('data.date_utilized')->label('Date Utilized/Implemented')->date('m/d/Y'),
                Tables\Columns\TextColumn::make('data.contribution_percentage')
                    ->label('% Contribution')
                    ->suffix('%'),
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
                ->modalHeading(fn(): string => 'Submit New Translated Output (' . Str::of($this->activeTable)->replace('_', ' ')->title() . ')')
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
                ->modalHeading(fn(): string => 'Edit Translated Output (' . Str::of($this->activeTable)->replace('_', ' ')->title() . ')')
                ->modalWidth('3xl')
                ->visible($this->getActionVisibility()),
            DeleteAction::make()
                ->after(fn() => $this->mount())
                ->visible($this->getActionVisibility()),
        ];
    }

    protected function getFormSchema(): array
    {
        $schema = [
            Textarea::make('data.title')
                ->label('Title of Research')
                ->maxLength(255)
                ->required()
                ->columnSpanFull(),
            DatePicker::make('data.date_completed')
                ->label('Date Completed')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->maxDate(now())
                ->required(),
            TextInput::make('data.funding_source')
                ->label('Funding Source')
                ->maxLength(150)
                ->required(),
            Textarea::make('data.project_name')
                ->label('Name of Project, Policy or Product')
                ->maxLength(255)
                ->required()
                ->columnSpanFull(),
            DatePicker::make('data.date_utilized')
                ->label('Date Utilized / Implemented / Adopted / Developed')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->maxDate(now())
                ->required(),
        ];

        if ($this->activeTable === 'contributor') {
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
