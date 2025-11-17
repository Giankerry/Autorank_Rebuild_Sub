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

class ExhibitionWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a2.exhibition-widget';

    public function getGoogleDriveFolderPath(): array
    {
        return [$this->getKACategory(), 'C: Creative Work', 'Exhibition'];
    }

    protected function getKACategory(): string
    {
        return 'KRA II';
    }

    protected function getActiveSubmissionType(): string
    {
        return 'creative-exhibition';
    }

    protected function getOptionsMaps(): array
    {
        return [
            'classification' => [
                'visual_arts' => 'Visual Arts',
                'architecture' => 'Architecture',
                'film' => 'Film',
                'multimedia' => 'Multimedia',
            ],
            'creative_type' => [
                'painting_drawing' => 'Painting/Drawing',
                'sculpture' => 'Sculpture',
                'photography' => 'Photography',
                'architectural_design' => 'Architectural Design',
                'film_short_film' => 'Film/Short Film',
                'multimedia' => 'Multimedia',
                'others' => 'Others'
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        $maps = $this->getOptionsMaps();

        return [
            'Classification' => $maps['classification'],
            'Creative Type' => $maps['creative_type'],
            'Date Exhibited' => 'm/d/Y',
        ];
    }

    public function table(Table $table): Table
    {
        $maps = $this->getOptionsMaps();

        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading('Exhibition Submissions')
            ->columns([
                Tables\Columns\TextColumn::make('data.title')->label('Title')->wrap(),
                Tables\Columns\TextColumn::make('data.classification')
                    ->label('Classification')
                    ->formatStateUsing(fn(?string $state): string => $maps['classification'][$state] ?? Str::of($state)->replace('_', ' ')->title())
                    ->badge(),
                Tables\Columns\TextColumn::make('data.creative_type')
                    ->label('Creative Type')
                    ->formatStateUsing(fn(?string $state): string => $maps['creative_type'][$state] ?? Str::of($state)->replace('_', ' ')->title())
                    ->badge(),
                Tables\Columns\TextColumn::make('data.date_exhibited')->label('Exhibition Date')->date('m/d/Y'),
                ScoreColumn::make('score'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
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
                    ->modalHeading('Submit New Creative Work (Exhibition)')
                    ->modalWidth('3xl')
                    ->after(fn() => $this->mount()),
            ])
            ->actions([
                ViewSubmissionFilesAction::make(),
                Tables\Actions\EditAction::make()
                    ->form($this->getFormSchema())
                    ->modalHeading('Edit Creative Work (Exhibition)')
                    ->modalWidth('3xl')
                    ->visible($this->getActionVisibility()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn() => $this->mount())
                    ->visible($this->getActionVisibility()),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return Submission::query()
            ->where('user_id', Auth::id())
            ->where('type', $this->getActiveSubmissionType())
            ->where('application_id', $this->selectedApplicationId);
    }

    protected function getFormSchema(): array
    {
        $maps = $this->getOptionsMaps();

        return [
            Textarea::make('data.title')
                ->label('Title of Creative Work')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Select::make('data.classification')
                ->label('Classification')
                ->options($maps['classification'])
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn(callable $set) => $set('data.creative_type', null)),
            Select::make('data.creative_type')
                ->label('Type of Creative Work')
                ->options(function (Get $get) use ($maps): array {
                    return match ($get('data.classification')) {
                        'visual_arts' => [
                            'painting_drawing' => $maps['creative_type']['painting_drawing'],
                            'sculpture' => $maps['creative_type']['sculpture'],
                            'photography' => $maps['creative_type']['photography'],
                            'others' => $maps['creative_type']['others'],
                        ],
                        'architecture' => [
                            'architectural_design' => $maps['creative_type']['architectural_design'],
                            'others' => $maps['creative_type']['others'],
                        ],
                        'film' => [
                            'film_short_film' => $maps['creative_type']['film_short_film'],
                            'others' => $maps['creative_type']['others'],
                        ],
                        'multimedia' => [
                            'multimedia' => $maps['creative_type']['multimedia'],
                            'others' => $maps['creative_type']['others'],
                        ],
                        default => [],
                    };
                })
                ->searchable()
                ->required()
                ->visible(fn(Get $get): bool => !empty($get('data.classification'))),
            DatePicker::make('data.date_exhibited')
                ->label('Exhibition Date')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->maxDate(now())
                ->required(),
            TextInput::make('data.venue')
                ->label('Venue of Exhibit')
                ->maxLength(255)
                ->required(),
            TextInput::make('data.organizer')
                ->label('Organizer of the Event')
                ->maxLength(255)
                ->required(),

            $this->getKRAFileUploadComponent(),
        ];
    }
}
