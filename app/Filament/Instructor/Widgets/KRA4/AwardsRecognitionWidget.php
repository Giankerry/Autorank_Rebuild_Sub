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
use App\Filament\Traits\AutofillDocument;

class AwardsRecognitionWidget extends BaseKRAWidget
{
    use AutofillDocument;

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

    //map for autofill certificate data
    protected function mapCertificateDataToForm(Set $set, Get $get, ?string $credentialType, ?string $dateCompleted, ?string $issuingOrg, ?string $venue): void
    {
        $set('data.name', $credentialType ?? $get('data.name'));
        $set('data.date_given', $dateCompleted ?? $get('data.date_given'));
        $set('data.awarding_body', $issuingOrg ?? $get('data.awarding_body'));
        $set('data.venue', $venue ?? $get('data.venue'));
    }

    protected function mapMoaDataToForm(Set $set, Get $get, ?string $partnerName, ?string $startDate, ?string $expirationDate, ?string $scope): void
    {
        // This widget only handles Certificate data.
        Notification::make()->title('Document Type Mismatch')->body('The uploaded document is a Memorandum of Agreement (MOA). This form requires a Certificate or Award Document.')->warning()->send();
    }

    protected function mapResearchDataToForm(Set $set, Get $get, ?string $title, ?string $authorList, ?string $publisher, ?string $datePublished, ?string $documentType): void
    {
        //for cert data
        Notification::make()->title('Document Type Mismatch')->body('The uploaded document is a Research Paper/Thesis. This form requires a Certificate or Award Document.')->warning()->send();
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
                    // Column 1 & 2: File Upload 
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

                    //call autofill function
                    $this->getAutofillAction(),
                ]),
        ];
    }
}
