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

class NonPatentableInventionsWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a2.non-patentable-inventions-widget';

    public ?string $activeTable = 'software_new_sole';

    public function updatedActiveTable(): void
    {
        $this->resetTable();
    }

    public function getGoogleDriveFolderPath(): array
    {
        $kra = $this->getKACategory();
        $baseFolder = 'B: Non-Patentable Inventions';

        switch ($this->activeTable) {
            case 'software_new_sole':
                return [$kra, $baseFolder, 'Software - New (Sole)'];
            case 'software_new_co':
                return [$kra, $baseFolder, 'Software - New (Co-Developer)'];
            case 'software_updated':
                return [$kra, $baseFolder, 'Software - Updated'];
            case 'plant_animal_sole':
                return [$kra, $baseFolder, 'Plant-Animal - Sole'];
            case 'plant_animal_co':
                return [$kra, $baseFolder, 'Plant-Animal - Co-Developer'];
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
            'software_new_sole' => 'invention-software-new-sole',
            'software_new_co' => 'invention-software-new-co',
            'software_updated' => 'invention-software-updated',
            'plant_animal_sole' => 'invention-plant-animal-sole',
            'plant_animal_co' => 'invention-plant-animal-co',
            default => 'invention-software-new-sole',
        };
    }

    protected function getOptionsMaps(): array
    {
        return [
            'developer_role' => [
                'Sole Developer' => 'Sole Developer',
                'Co-developer' => 'Co-developer'
            ],
            'type' => [
                'plant' => 'Plant',
                'animal' => 'Animal',
                'microbe' => 'Microbe'
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        $maps = $this->getOptionsMaps();

        return [
            'Developer Role' => $maps['developer_role'],
            'Type' => $maps['type'],
            'Date Copyrighted' => 'm/d/Y',
            'Date Utilized' => 'm/d/Y',
            'Date Completed' => 'm/d/Y',
            'Date Registered' => 'm/d/Y',
            'Date Propagation' => 'm/d/Y',
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
            ->replace(' co ', ' Co-Developer ')
            ->title() . ' Submissions';
    }

    protected function getTableColumns(): array
    {
        $columns = [];
        $columns[] = Tables\Columns\TextColumn::make('data.name')->label('Name')->wrap();

        if (Str::startsWith($this->activeTable, 'software')) {
            $columns[] = Tables\Columns\TextColumn::make('data.copyright_no')->label('Copyright No.');
            $columns[] = Tables\Columns\TextColumn::make('data.date_copyrighted')->label('Date Copyrighted')->date('m/d/Y');
            $columns[] = Tables\Columns\TextColumn::make('data.date_utilized')->label('Date Utilized')->date('m/d/Y');
            $columns[] = Tables\Columns\TextColumn::make('data.end_user')->label('End User(s)');
        } elseif (Str::startsWith($this->activeTable, 'plant_animal')) {
            $columns[] = Tables\Columns\TextColumn::make('data.type')->label('Type')->formatStateUsing(fn(?string $state): string => $this->getOptionsMaps()['type'][$state] ?? Str::title($state ?? ''))->badge();
            $columns[] = Tables\Columns\TextColumn::make('data.date_completed')->label('Date Completed')->date('m/d/Y');
            $columns[] = Tables\Columns\TextColumn::make('data.date_registered')->label('Date Registered')->date('m/d/Y');
            $columns[] = Tables\Columns\TextColumn::make('data.date_propagation')->label('Date Propagation')->date('m/d/Y');
        }

        if ($this->activeTable === 'software_new_co' || $this->activeTable === 'plant_animal_co') {
            $columns[] = Tables\Columns\TextColumn::make('data.contribution_percentage')->label('% Contribution')->suffix('%');
        }

        if ($this->activeTable === 'software_updated') {
            $columns[] = Tables\Columns\TextColumn::make('data.developer_role')->label('Role')->formatStateUsing(fn(?string $state): string => $this->getOptionsMaps()['developer_role'][$state] ?? $state)->badge();
            $columns[] = Tables\Columns\TextColumn::make('data.update_details')->label('Update Details')->wrap()->limit(50);
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

        if (Str::startsWith($this->activeTable, 'software')) {
            $schema = [
                TextInput::make('data.name')->label('Name of the Software')->maxLength(255)->required()->columnSpanFull(),
                TextInput::make('data.copyright_no')->label('Copyright No.')->maxLength(100)->required(),
                DatePicker::make('data.date_copyrighted')
                    ->label('Date Copyrighted')
                    ->native(false)
                    ->displayFormat('m/d/Y')
                    ->maxDate(now())
                    ->required(),
                DatePicker::make('data.date_utilized')
                    ->label('Date Utilized (If applicable)')
                    ->native(false)
                    ->displayFormat('m/d/Y')
                    ->maxDate(now()),
                TextInput::make('data.end_user')->label('Name of End User(s)')->maxLength(255)->required(),
            ];
            if ($this->activeTable === 'software_new_co') {
                $schema[] = TrimmedIntegerInput::make('data.contribution_percentage')
                    ->label('% Contribution')
                    ->minValue(1)
                    ->maxValue(100)
                    ->required();
            } elseif ($this->activeTable === 'software_updated') {
                $schema[] = Select::make('data.developer_role')
                    ->label('Developer Role')
                    ->options($maps['developer_role'])
                    ->searchable()
                    ->required();
                $schema[] = Textarea::make('data.update_details')->label('Specify New Features / Update Details')->required()->columnSpanFull();
            }
        } elseif (Str::startsWith($this->activeTable, 'plant_animal')) {
            $schema = [
                TextInput::make('data.name')->label('Name of Plant Variety, Animal Breed, or Microbial Strain')->maxLength(255)->required()->columnSpanFull(),
                Select::make('data.type')
                    ->label('Type')
                    ->options($maps['type'])
                    ->searchable()
                    ->required(),
                DatePicker::make('data.date_completed')
                    ->label('Date Completed')
                    ->native(false)
                    ->displayFormat('m/d/Y')
                    ->maxDate(now())
                    ->required(),
                DatePicker::make('data.date_registered')
                    ->label('Date Registered')
                    ->native(false)
                    ->displayFormat('m/d/Y')
                    ->maxDate(now())
                    ->required(),
                DatePicker::make('data.date_propagation')
                    ->label('Date of Propagation based on Certification')
                    ->native(false)
                    ->displayFormat('m/d/Y')
                    ->maxDate(now())
                    ->required(),
            ];
            if ($this->activeTable === 'plant_animal_co') {
                $schema[] = TrimmedIntegerInput::make('data.contribution_percentage')
                    ->label('% Contribution')
                    ->minValue(1)
                    ->maxValue(100)
                    ->required();
            }
        }

        $schema[] = $this->getKRAFileUploadComponent();

        return $schema;
    }
}
