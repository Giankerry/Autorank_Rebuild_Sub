<?php

namespace App\Filament\Instructor\Widgets\KRA4;

use App\Models\Submission;
use App\Services\DocumentAiService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Filament\Instructor\Widgets\BaseKRAWidget;
use App\Tables\Columns\ScoreColumn;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Log;

class AwardsRecognitionWidget extends BaseKRAWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a4.awards-recognition-widget';

    protected function getKACategory(): string
    {
        return 'KRA IV';
    }

    protected function getActiveSubmissionType(): string
    {
        return 'profdev-award-recognition';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading('Awards and Recognition')
            ->columns([
                Tables\Columns\TextColumn::make('data.name')->label('Name of the Award')->wrap(),
                Tables\Columns\TextColumn::make('data.scope')
                    ->label('Scope')
                    ->formatStateUsing(fn(?string $state): string => Str::title($state))
                    ->badge(),
                Tables\Columns\TextColumn::make('data.awarding_body')->label('Award-Giving Body'),
                Tables\Columns\TextColumn::make('data.date_given')->label('Date Given')->date(),
                // Display the venue in the table
                Tables\Columns\TextColumn::make('data.venue')->label('Venue of Ceremony'),
                ScoreColumn::make('score'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add')
                    ->form($this->getFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();
                        $data['application_id'] = $this->selectedApplicationId;
                        $data['category'] = $this->getKACategory();
                        $data['type'] = $this->getActiveSubmissionType();
                        return $data;
                    })
                    ->modalHeading('Submit New Award/Recognition')
                    ->modalWidth('3xl')
                    ->hidden(fn(): bool => $this->submissionExistsForCurrentType())
                    ->after(fn() => $this->mount()),
            ])
            ->actions([
                EditAction::make()
                    ->form($this->getFormSchema())
                    ->modalHeading('Edit Award/Recognition')
                    ->modalWidth('3xl')
                    ->visible($this->getActionVisibility()),
                DeleteAction::make()
                    ->after(fn() => $this->mount())
                    ->visible($this->getActionVisibility()),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return Submission::query()
            ->where('user_id', Auth::id())
            ->where('category', $this->getKACategory())
            ->where('type', $this->getActiveSubmissionType())
            ->where('application_id', $this->selectedApplicationId);
    }

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('data.name')
                ->label('Name of the Award')
                ->required()
                ->maxLength(255)
                ->columnSpanFull()
                ->live(),

            Select::make('data.scope')
                ->label('Scope of the Award')
                ->options([
                    'institutional' => 'Institutional',
                    'local' => 'Local',
                    'regional' => 'Regional',
                ])
                ->searchable()
                ->required(),

            TextInput::make('data.awarding_body')
                ->label('Award-Giving Body/Organization')
                ->required()
                ->maxLength(255)
                ->live(),

            DatePicker::make('data.date_given')
                ->label('Date the Award was Given')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now())
                ->live(),

            TextInput::make('data.venue')
                ->label('Venue of the Award Ceremony')
                ->required()
                ->maxLength(255)
                ->live(),

            Grid::make(3)
                ->columnSpanFull()
                ->schema([
                    FileUpload::make('google_drive_file_id')
                        ->label('Proof Document(s) (e.g., Certificate, Plaque Photo)')
                        ->multiple()
                        ->reorderable()
                        ->required()
                        ->disk('private')
                        ->directory('proof-documents/kra4-awards')
                        ->acceptedFileTypes(['application/pdf', 'image/*'])
                        ->reactive()
                        ->columnSpan(2),

                    Actions::make([
                        //Autofill Button
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

                                        // Autofill
                                        $set('data.name', $credentialType ?? $get('data.name'));
                                        $set('data.date_given', $dateCompleted ?? $get('data.date_given'));
                                        $set('data.awarding_body', $issuingOrg ?? $get('data.awarding_body'));
                                        $set('data.venue', $venue ?? $get('data.venue'));

                                        Notification::make()->title('AI Extraction Successful')->body('Certificate details were extracted and autofilled. Please review and save.')->success()->send();
                                    } else {
                                        Notification::make()->title('Invalid Certificate')->body('The file was not recognized as a valid certificate.')->warning()->send();
                                    }
                                } catch (\Exception $e) {
                                    Log::error("Document AI Button Error: " . $e->getMessage());
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
