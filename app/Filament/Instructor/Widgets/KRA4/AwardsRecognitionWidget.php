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
                ->columnSpanFull(),

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
                ->maxLength(255),

            DatePicker::make('data.date_given')
                ->label('Date the Award was Given')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now()),

            TextInput::make('data.venue')
                ->label('Venue of the Award Ceremony')
                ->required()
                ->maxLength(255),

            //Autofill via Document AI Integration
            FileUpload::make('google_drive_file_id')
                ->label('Proof Document(s) (e.g., Certificate, Plaque Photo)')
                ->multiple()
                ->reorderable()
                ->required()
                ->disk('private')
                ->directory('proof-documents/kra4-awards')
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->columnSpanFull()
                ->reactive()
                ->afterStateUpdated(function (?array $state, Set $set, Get $get) {
                    $newlyUploadedFile = last($state) ?? null;

                    if (!$newlyUploadedFile instanceof TemporaryUploadedFile) {
                        return;
                    }

                    $docAiService = app(DocumentAiService::class);
                    $extractedData = $docAiService->processDocument($newlyUploadedFile);

                    if ($extractedData['IsCertificate'] ?? false) {
                        // Map your DocAI entity names → form fields
                        $credentialType = $extractedData['Credential_Type'] ?? null;
                        $dateCompleted = $extractedData['Date_Completed'] ?? $extractedData['Year_Issued'] ?? null;
                        $issuingOrg = $extractedData['Issuing_Organization'] ?? null;
                        $recipientName = $extractedData['User_Full_Name'] ?? null;
                        $serialNumber = $extractedData['Serial_Number'] ?? null;

                        // Autofill
                        $set('data.name', $credentialType ?? $get('data.name'));
                        $set('data.date_given', $dateCompleted ?? $get('data.date_given'));
                        $set('data.awarding_body', $issuingOrg ?? $get('data.awarding_body'));

                        Notification::make()
                            ->title('AI Extraction Successful')
                            ->body('Certificate details were extracted and autofilled successfully.')
                            ->success()
                            ->send();
                    } else {
                        // Invalid certificate
                        $currentFiles = collect($get('google_drive_file_id'))
                            ->except(count($get('google_drive_file_id')) - 1)
                            ->toArray();

                        $set('google_drive_file_id', $currentFiles);

                        Notification::make()
                            ->title('Invalid Document')
                            ->body('The uploaded file was not recognized as a valid certificate or diploma.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
