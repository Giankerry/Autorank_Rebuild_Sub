<?php

namespace App\Filament\Instructor\Widgets\KRA2;

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

class LiteraryPublicationWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a2.literary-publication-widget';

    protected function getGoogleDriveFolderPath(): array
    {
        return [$this->getKACategory(), 'C: Creative Work', 'Juried Design'];
    }

    protected function getKACategory(): string
    {
        return 'KRA II';
    }

    protected function getActiveSubmissionType(): string
    {
        return 'creative-literary-publication';
    }

    protected function getOptionsMaps(): array
    {
        return [
            'literary_type' => [
                'novel' => 'Novel',
                'short_story' => 'Short Story',
                'essay' => 'Essay',
                'poetry' => 'Poetry',
                'others' => 'Others',
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        return [
            'Literary Type' => $this->getOptionsMaps()['literary_type'],
            'Date Published' => 'm/d/Y',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading('Literary Publication Submissions')
            ->columns([
                Tables\Columns\TextColumn::make('data.title')->label('Title')->wrap(),
                Tables\Columns\TextColumn::make('data.literary_type')
                    ->label('Type')
                    ->formatStateUsing(fn(?string $state): string => $this->getOptionsMaps()['literary_type'][$state] ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('data.publisher')->label('Publisher'),
                Tables\Columns\TextColumn::make('data.date_published')->label('Date Published')->date('m/d/Y'),
                ScoreColumn::make('score'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add')
                    ->form($this->getFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();
                        $data['application_id'] = $this->selectedApplicationId;
                        $data['category'] = $this->getKACategory();
                        $data['type'] = $this->getActiveSubmissionType();
                        return $data;
                    })
                    ->modalHeading('Submit New Literary Publication')
                    ->modalWidth('3xl')
                    ->after(fn() => $this->mount()),
            ])
            ->actions([
                ViewSubmissionFilesAction::make(),
                Tables\Actions\EditAction::make()
                    ->form($this->getFormSchema())
                    ->modalHeading('Edit Literary Publication')
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
                ->label('Title of Literary Publication')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Select::make('data.literary_type')
                ->label('Type of Literary Publication')
                ->options($this->getOptionsMaps()['literary_type'])
                ->searchable()
                ->required(),
            TextInput::make('data.reviewer')
                ->label('Reviewer, Evaluator or Its Equivalent')
                ->maxLength(255)
                ->required(),
            TextInput::make('data.publication_name')
                ->label('Name of Publication')
                ->maxLength(255)
                ->required(),
            TextInput::make('data.publisher')
                ->label('Name of Publisher/Press')
                ->maxLength(255)
                ->required(),
            DatePicker::make('data.date_published')
                ->label('Date Published')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->maxDate(now())
                ->required(),

            $this->getKRAFileUploadComponent(),
        ];
    }
}
