<?php

namespace App\Filament\Instructor\Widgets\KRA4;

use App\Models\Submission;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
use App\Tables\Columns\ScoreColumn;
use App\Services\DocumentAiService;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Log; // Added Log for error handling

class ConferenceTrainingWidget extends BaseKRAWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a4.conference-training-widget';

    protected function getKACategory(): string
    {
        return 'KRA IV';
    }

    protected function getActiveSubmissionType(): string
    {
        return 'profdev-conference-training';
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
                    ->formatStateUsing(fn(?string $state): string => Str::title($state))
                    ->badge(),
                Tables\Columns\TextColumn::make('data.organizer')->label('Organizer'),
                Tables\Columns\TextColumn::make('data.date_activity')->label('Date of Activity')->date(),
                Tables\Columns\TextColumn::make('data.venue')->label('Venue of Activity'),
                ScoreColumn::make('score'),
            ])
            ->headerActions($this->getTableHeaderActions())
            ->actions($this->getTableActions());
    }

    protected function getTableQuery(): Builder
    {
        return Submission::query()
            ->where('user_id', Auth::id())
            ->where('category', $this->getKACategory())
            ->where('type', $this->getActiveSubmissionType())
            ->where('application_id', $this->selectedApplicationId);
    }

    protected function getTableHeaderActions(): array
    {
        return [
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
                ->modalHeading('Submit New Conference/Training Participation')
                ->modalWidth('3xl')
                ->after(fn() => $this->mount()),
        ];
    }

    protected function getTableActions(): array
    {
        return [
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

    protected function getFormSchema(): array
    {
        return [
            Textarea::make('data.name')
                ->label('Name of Conference/Training')
                ->required()
                ->maxLength(65535)
                ->columnSpanFull()
                ->live(),

            Select::make('data.scope')
                ->label('Scope')
                ->options([
                    'local' => 'Local',
                    'international' => 'International',
                ])
                ->searchable()
                ->required(),

            TextInput::make('data.organizer')
                ->label('Organizer/Sponsoring Body')
                ->required()
                ->maxLength(255)
                ->live(),

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
                    FileUpload::make('google_drive_file_id')
                        ->label('Proof Document(s) (e.g., Certificate of Participation)')
                        ->multiple()
                        ->reorderable()
                        ->required()
                        ->disk('private')
                        ->directory('proof-documents/kra4-training')
                        ->acceptedFileTypes(['application/pdf', 'image/*'])
                        ->reactive()
                        ->columnSpan(2),

                    // FIX: Wrap the Action component inside Filament\Forms\Components\Actions
                    Actions::make([
                        // Column 3: The dedicated Autofill Button
                        Action::make('autofill_certificate')
                            ->label('Autofill from Certificate')
                            ->icon('heroicon-s-sparkles')
                            ->color('warning')
                            ->action(function (Set $set, Get $get) {
                                $files = $get('google_drive_file_id');

                                if (empty($files)) {
                                    Notification::make()->title('No File Uploaded')->body('Please upload a certificate first.')->warning()->send();
                                    return;
                                }

                                // Find the last uploaded file instance to process
                                $fileToProcess = null;
                                foreach (array_reverse($files) as $file) {
                                    if ($file instanceof TemporaryUploadedFile) {
                                        $fileToProcess = $file;
                                        break;
                                    }
                                }

                                if (!$fileToProcess) {
                                    Notification::make()->title('File Not Ready')->body('Please ensure the file upload is complete or try reloading the form.')->warning()->send();
                                    return;
                                }

                                try {
                                    $docAiService = app(DocumentAiService::class);
                                    $extractedData = $docAiService->processDocument($fileToProcess);

                                    if ($extractedData['IsCertificate'] ?? false) {
                                        $credentialType = $extractedData['CredentialType'] ?? null;
                                        $dateCompleted = $extractedData['DateCompleted'] ?? $extractedData['YearIssued'] ?? null;
                                        $issuingOrg = $extractedData['IssuingOrganization'] ?? null;
                                        $venue = $extractedData['AwardVenue'] ?? null;

                                        // Autofill existing and new fields
                                        $set('data.name', $credentialType ?? $get('data.name'));
                                        $set('data.date_activity', $dateCompleted ?? $get('data.date_activity'));
                                        $set('data.organizer', $issuingOrg ?? $get('data.organizer'));
                                        $set('data.venue', $venue ?? $get('data.venue'));

                                        Notification::make()->title('AI Extraction Successful')->body('Certificate details were extracted and autofilled. Please review and save.')->success()->send();
                                    } else {
                                        Notification::make()->title('Invalid Certificate')->body('The file was not recognized as a valid certificate.')->warning()->send();
                                    }
                                } catch (\Exception $e) {
                                    Log::error("Document AI Button Error (Conf/Train): " . $e->getMessage());
                                    Notification::make()->title('Document AI Error')->body('Failed to process document. Check logs for details.')->danger()->send();
                                }
                            })
                            ->extraAttributes([
                                'class' => 'mt-8',
                            ])
                            ->visible(fn(Get $get) => !empty($get('google_drive_file_id'))),
                    ])->columnSpan(1)
                ]),
        ];
    }
}
