<?php

namespace App\Filament\Instructor\Widgets\KRA4;

use App\Models\Application;
use App\Models\Submission;
use App\Services\DocumentAiService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Log;
use App\Filament\Traits\AutofillDocument;
use Filament\Forms\Components\FileUpload;

class ConferenceTrainingWidget extends BaseKRAWidget
{
    use AutofillDocument;
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a4.conference-training-widget';

    public function getGoogleDriveFolderPath(): array
    {
        return [$this->getKACategory(), 'B. Continuing Development'];
    }

    protected function getKACategory(): string
    {
        return 'KRA IV';
    }

    protected function getActiveSubmissionType(): string
    {
        return 'profdev-conference-training';
    }

    protected function getOptionsMaps(): array
    {
        return [
            'scope' => [
                'local' => 'Local',
                'international' => 'International',
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        return [
            'Scope' => $this->getOptionsMaps()['scope'],
            'Date' => 'm/d/Y',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading('Conference/Training Participation')
            ->columns([
                Tables\Columns\TextColumn::make('data.name')->label('Name of Conference/Training')->wrap(),
                Tables\Columns\TextColumn::make('data.scope')
                    ->label('Scope')
                    ->formatStateUsing(fn(?string $state): string => $this->getOptionsMaps()['scope'][$state] ?? Str::title($state ?? ''))
                    ->badge(),
                Tables\Columns\TextColumn::make('data.organizer')->label('Organizer'),
                Tables\Columns\TextColumn::make('data.date_activity')->label('Date of Activity')->date('m/d/Y'),
                Tables\Columns\TextColumn::make('data.venue')->label('Venue of Activity'),
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
                ->modalHeading('Submit New Conference/Training Participation')
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
                ->modalHeading('Edit Conference/Training Participation')
                ->modalWidth('3xl')
                ->visible($this->getActionVisibility()),
            Tables\Actions\DeleteAction::make()
                ->after(fn() => $this->mount())
                ->visible($this->getActionVisibility()),
        ];
    }

    protected function mapCertificateDataToForm($set, $get, ?string $credentialType, ?string $dateCompleted, ?string $issuingOrg, ?string $venue): void
    {
        $set('data.name', $credentialType ?? $get('data.name'));
        $set('data.date_activity', $dateCompleted ?? $get('data.date_activity'));
        $set('data.organizer', $issuingOrg ?? $get('data.organizer'));
        $set('data.venue', $venue ?? $get('data.venue'));
    }

    protected function mapMoaDataToForm($set, $get, ?string $partnerName, ?string $startDate, ?string $expirationDate, ?string $scope): void
    {
        Notification::make()->title('Document Type Mismatch')->body('The uploaded document is a Memorandum of Agreement (MOA). This form requires a Certificate or Training Document.')->warning()->send();
    }

    protected function mapResearchDataToForm($set, $get, ?string $title, ?string $authorList, ?string $publisher, ?string $datePublished, ?string $documentType): void
    {
        Notification::make()->title('Document Type Mismatch')->body('The uploaded document is a Research Paper/Thesis. This form requires a Certificate or Training Document.')->warning()->send();
    }


    protected function getFormSchema(): array
    {
        return [
            Textarea::make('data.name')
                ->label('Name of Conference/Training')
                ->required()->maxLength(65535)->columnSpanFull()->live(),

            Select::make('data.scope')
                ->label('Scope')
                ->options($this->getOptionsMaps()['scope'])
                ->searchable()
                ->required(),
            TextInput::make('data.organizer')
                ->label('Organizer/Sponsoring Body')
                ->required()->maxLength(255)->live(),

            DatePicker::make('data.date_activity')
                ->label('Date of Activity')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now())
                ->live(),

            TextInput::make('data.venue')
                ->label('Venue of Activity')
                ->maxLength(255)
                ->columnSpanFull()
                ->live(),

            Grid::make(3)
                ->columnSpanFull()
                ->schema([
                    $this->getKRAFileUploadComponent()->columnSpan(2),
                    $this->getAutofillAction(),
                ]),
        ];
    }
}
