<?php

namespace App\Filament\Instructor\Widgets\KRA2;

use App\Models\Application;
use App\Models\Submission;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use App\Filament\Instructor\Widgets\BaseKRAWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Tables\Columns\ScoreColumn;
use App\Filament\Traits\HandlesKRAFileUploads;
use App\Tables\Actions\ViewSubmissionFilesAction;

class PerformingArtWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a2.performing-art-widget';

    public function getGoogleDriveFolderPath(): array
    {
        return [$this->getKACategory(), 'C: Creative Work', 'Juried Design'];
    }

    protected function getKACategory(): string
    {
        return 'KRA II';
    }

    protected function getActiveSubmissionType(): string
    {
        return 'creative-performing-art';
    }

    protected function getOptionsMaps(): array
    {
        return [
            'art_type' => [
                'song_music' => 'Song/Music',
                'choreography_dance' => 'Choreography/Dance',
                'drama_theater' => 'Drama/Theater',
                'others' => 'Others',
            ],
            'classification' => [
                'new_creation' => 'New Creation',
                'own_work' => 'Own Work',
                'work_of_others' => 'Work of Others',
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        $maps = $this->getOptionsMaps();

        return [
            'Art Type' => $maps['art_type'],
            'Classification' => $maps['classification'],
            'Date Performed' => 'm/d/Y',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading('Performing Art Submissions')
            ->columns([
                Tables\Columns\TextColumn::make('data.title')->label('Title')->wrap(),
                Tables\Columns\TextColumn::make('data.art_type')
                    ->label('Art Type')
                    ->formatStateUsing(fn(?string $state): string => $this->getOptionsMaps()['art_type'][$state] ?? Str::of($state)->replace('_', ' ')->title())
                    ->badge(),
                Tables\Columns\TextColumn::make('data.classification')
                    ->label('Classification')
                    ->formatStateUsing(fn(?string $state): string => $this->getOptionsMaps()['classification'][$state] ?? Str::of($state)->replace('_', ' ')->title()),
                Tables\Columns\TextColumn::make('data.date_performed')->label('Date Performed')->date('m/d/Y'),
                ScoreColumn::make('score'),
            ])
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

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\CreateAction::make()
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
                ->modalHeading('Submit New Creative Performing Artwork')
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
            Tables\Actions\EditAction::make()
                ->form($this->getFormSchema())
                ->modalHeading('Edit Creative Performing Artwork')
                ->modalWidth('3xl')
                ->visible($this->getActionVisibility()),
            Tables\Actions\DeleteAction::make()
                ->after(fn() => $this->mount())
                ->visible($this->getActionVisibility()),
        ];
    }

    protected function getFormSchema(): array
    {
        $maps = $this->getOptionsMaps();

        return [
            Textarea::make('data.title')
                ->label('Title of Creative Performing Art')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Select::make('data.art_type')
                ->label('Type of Performing Art')
                ->options($maps['art_type'])
                ->searchable()
                ->required(),
            Select::make('data.classification')
                ->label('Classification')
                ->options($maps['classification'])
                ->searchable()
                ->required()
                ->live(),
            DatePicker::make('data.date_performed')
                ->label('Date Copyrighted / Date Performed')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->maxDate(now())
                ->required(),
            TextInput::make('data.venue')
                ->label('Venue of Performance')
                ->maxLength(255)
                ->required()
                ->hidden(fn(Get $get): bool => $get('data.classification') === 'new_creation'),
            TextInput::make('data.organizer')
                ->label('Organizer of the Event (or Publisher)')
                ->maxLength(255)
                ->required(),

            $this->getKRAFileUploadComponent(),
        ];
    }
}
