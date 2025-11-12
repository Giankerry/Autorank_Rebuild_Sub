<?php

namespace App\Filament\Instructor\Widgets\KRA3;

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
use Filament\Forms\Get;
use App\Filament\Traits\HandlesKRAFileUploads;
use App\Tables\Actions\ViewSubmissionFilesAction;
use App\Services\DocumentAiService; //imports for Doc AI and autofill
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Log;
use App\Filament\Traits\AutofillDocument;
use Filament\Forms\Components\FileUpload;

class LinkagesWidget extends BaseKRAWidget
{
    use AutofillDocument;
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a3.linkages-widget';

    protected function getGoogleDriveFolderPath(): array
    {
        return [$this->getKACategory(), 'A. Linkages, Networking and Partnership'];
    }

    protected function getKACategory(): string
    {
        return 'KRA III';
    }

    protected function getActiveSubmissionType(): string
    {
        return 'extension-linkage';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading('Linkages, Networking and Partnership Submissions')
            ->columns([
                Tables\Columns\TextColumn::make('data.partner_name')->label('Name of Partner')->wrap(),
                Tables\Columns\TextColumn::make('data.faculty_role')
                    ->label('Faculty Role')
                    ->formatStateUsing(fn(?string $state): string => Str::of($state)->replace('_', ' ')->title())
                    ->badge(),
                Tables\Columns\TextColumn::make('data.moa_start')->label('MOA Start')->date(),
                Tables\Columns\TextColumn::make('data.moa_expiration')->label('MOA End')->date(),
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
                ->modalHeading('Submit New Linkage/Partnership')
                ->modalWidth('3xl')
                ->after(fn() => $this->mount()),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewSubmissionFilesAction::make(),
            Tables\Actions\EditAction::make()
                ->form($this->getFormSchema())
                ->modalHeading('Edit Linkage/Partnership')
                ->modalWidth('3xl')
                ->visible($this->getActionVisibility()),
            Tables\Actions\DeleteAction::make()
                ->after(fn() => $this->mount())
                ->visible($this->getActionVisibility()),
        ];
    }

    // Handles Certificate documents 
    protected function mapCertificateDataToForm(Set $set, Get $get, ?string $credentialType, ?string $dateCompleted, ?string $issuingOrg, ?string $venue): void
    {
        Notification::make()->title('Document Type Mismatch')->body('The uploaded document is a Certificate. This form requires a Memorandum of Agreement (MOA).')->warning()->send();
    }

    // handles MOA documents 
    protected function mapMoaDataToForm(Set $set, Get $get, ?string $partnerName, ?string $startDate, ?string $expirationDate, ?string $scope): void
    {
        $set('data.partner_name', $partnerName ?? $get('data.partner_name'));
        $set('data.moa_start', $startDate ?? $get('data.moa_start'));
        $set('data.moa_expiration', $expirationDate ?? $get('data.moa_expiration'));

        if (empty($get('data.activities')) && !empty($scope)) {
            $set('data.activities', "Activities related to the partnership scope: " . $scope);
        }
    }

    protected function mapResearchDataToForm(Set $set, Get $get, ?string $title, ?string $authorList, ?string $publisher, ?string $datePublished, ?string $documentType): void
    {
        Notification::make()->title('Document Type Mismatch')->body('The uploaded document is a Research Paper/Thesis. This form requires a Memorandum of Agreement (MOA).')->warning()->send();
    }


    protected function getFormSchema(): array
    {
        return [
            TextInput::make('data.partner_name')
                ->label('Name of Partner Institution/Organization')
                ->required()
                ->maxLength(255)
                ->live(),

            Select::make('data.faculty_role')
                ->label('Faculty Role in the Linkage')
                ->options([
                    'lead_coordinator' => 'Lead Coordinator',
                    'assistant_coordinator' => 'Assistant Coordinator',
                ])
                ->searchable()
                ->required(),

            DatePicker::make('data.moa_start')
                ->label('MOA Start Date')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now())
                ->live(),

            DatePicker::make('data.moa_expiration')
                ->label('MOA Expiration Date')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->minDate(fn(Get $get) => $get('data.moa_start'))
                ->live(),

            Textarea::make('data.activities')
                ->label('Activities Conducted Based on MOA')
                ->helperText('Not necessarily involving the faculty.')
                ->required()
                ->maxLength(65535)
                ->columnSpanFull(),

            DatePicker::make('data.activity_date')
                ->label('Date of Activity')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now()),

            Grid::make(3)
                ->columnSpanFull()
                ->schema([
                    $this->getKRAFileUploadComponent()->columnSpan(2),
                    $this->getAutofillAction(), 
                ]),
        ];
    }
}
