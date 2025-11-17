<?php

namespace App\Filament\Instructor\Widgets\KRA2;

use App\Models\Application;
use App\Models\Submission;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Instructor\Widgets\BaseKRAWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Tables\Columns\ScoreColumn;
use App\Filament\Traits\HandlesKRAFileUploads;
use App\Tables\Actions\ViewSubmissionFilesAction;

class JuriedDesignWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a2.juried-design-widget';

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
        return 'creative-juried-design';
    }

    protected function getOptionsMaps(): array
    {
        return [
            'classification' => [
                'architecture' => 'Architecture',
                'engineering' => 'Engineering',
                'industrial_design' => 'Industrial Design',
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        return [
            'Classification' => $this->getOptionsMaps()['classification'],
            'Activity Date' => 'm/d/Y',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading('Juried Design Submissions')
            ->columns([
                Tables\Columns\TextColumn::make('data.title')->label('Title')->wrap(),
                Tables\Columns\TextColumn::make('data.classification')
                    ->label('Classification')
                    ->formatStateUsing(fn(?string $state): string => $this->getOptionsMaps()['classification'][$state] ?? Str::of($state)->replace('_', ' ')->title())
                    ->badge(),
                Tables\Columns\TextColumn::make('data.reviewer')->label('Reviewer/Evaluator'),
                Tables\Columns\TextColumn::make('data.date_activity')->label('Activity Date')->date('m/d/Y'),
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
                    ->modalHeading('Submit New Juried Design')
                    ->modalWidth('3xl')
                    ->after(fn() => $this->mount()),
            ])
            ->actions([
                ViewSubmissionFilesAction::make(),
                Tables\Actions\EditAction::make()
                    ->form($this->getFormSchema())
                    ->modalHeading('Edit Juried Design')
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
        return [
            Textarea::make('data.title')
                ->label('Title of Design')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Select::make('data.classification')
                ->label('Classification')
                ->options($this->getOptionsMaps()['classification'])
                ->searchable()
                ->required(),
            TextInput::make('data.reviewer')
                ->label('Reviewer, Evaluator or Its Equivalent')
                ->maxLength(255)
                ->required(),
            DatePicker::make('data.date_activity')
                ->label('Activity/Exhibition Date')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->maxDate(now())
                ->required(),
            TextInput::make('data.venue')
                ->label('Venue of Activity/Exhibit')
                ->maxLength(255)
                ->required(),
            TextInput::make('data.organizer')
                ->label('Organizer')
                ->maxLength(255)
                ->required(),

            $this->getKRAFileUploadComponent(),
        ];
    }
}
