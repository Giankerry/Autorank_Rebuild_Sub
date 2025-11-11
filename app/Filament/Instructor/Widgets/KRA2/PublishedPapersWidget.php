<?php

namespace App\Filament\Instructor\Widgets\KRA2;

use App\Models\Submission;
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
use App\Forms\Components\TrimmedIntegerInput;
use App\Tables\Columns\ScoreColumn;
use App\Filament\Traits\HandlesKRAFileUploads;
use App\Tables\Actions\ViewSubmissionFilesAction;

// NEW IMPORTS for Autofill
use App\Filament\Traits\AutofillDocument;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Components\FileUpload; // Keep this import for the trait

class PublishedPapersWidget extends BaseKRAWidget
{
    use AutofillDocument;
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a2.published-papers-widget';

    public ?string $activeTable = 'sole_authorship';

    protected function getGoogleDriveFolderPath(): array
    {
        return [$this->getKACategory(), 'A. Published Papers'];
    }

    public function updatedActiveTable(): void
    {
        $this->resetTable();
    }

    protected function getKACategory(): string
    {
        return 'KRA II';
    }

    protected function getActiveSubmissionType(): string
    {
        return $this->activeTable === 'sole_authorship'
            ? 'research-sole-authorship'
            : 'research-co-authorship';
    }
    protected function mapCertificateDataToForm(Set $set, Get $get, ?string $credentialType, ?string $dateCompleted, ?string $issuingOrg, ?string $venue): void
    {
        Notification::make()->title('Document Type Mismatch')->body('The uploaded document is a Certificate. This form requires a Research Paper or Thesis.')->warning()->send();
    }

    protected function mapMoaDataToForm(Set $set, Get $get, ?string $partnerName, ?string $startDate, ?string $expirationDate, ?string $scope): void
    {
        Notification::make()->title('Document Type Mismatch')->body('The uploaded document is an MOA. This form requires a Research Paper or Thesis.')->warning()->send();
    }

    protected function mapResearchDataToForm(Set $set, Get $get, ?string $title, ?string $authorList, ?string $publisher, ?string $datePublished, ?string $documentType): void
    {
        $set('data.title', $title ?? $get('data.title'));
        $set('data.journal_name', $publisher ?? $get('data.journal_name'));
        $set('data.date_published', $datePublished ?? $get('data.date_published'));

        if ($authorList) {
            Notification::make()->title('Authors Extracted')->body('Authors: ' . $authorList . ' (Please enter manually if required).')->info()->send();
        }

        $outputType = 'journal_article';
        $docTypeUpper = Str::upper($documentType ?? '');

        if (Str::contains($docTypeUpper, ['THESIS', 'DISSERTATION', 'MONOGRAPH'])) {
            $outputType = 'monograph';
        } elseif (Str::contains($docTypeUpper, ['BOOK'])) {
            $outputType = 'book';
        }
        $set('data.output_type', $outputType);
    }


    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading($this->activeTable === 'sole_authorship' ? 'Published Papers (Sole Authorship)' : 'Published Papers (Co-Authorship)')
            ->columns([
                Tables\Columns\TextColumn::make('data.title')->label('Title')->wrap()->toggleable(),
                Tables\Columns\TextColumn::make('data.output_type')
                    ->label('Type')
                    ->formatStateUsing(fn(?string $state): string => Str::of($state)->replace('_', ' ')->title())
                    ->badge(),
                Tables\Columns\TextColumn::make('data.journal_name')->label('Journal/Publisher')->wrap()->toggleable(),
                Tables\Columns\TextColumn::make('data.date_published')->label('Date Published')->date()->toggleable(),
                Tables\Columns\TextColumn::make('data.contribution_percentage')->label('% Contribution')->visible($this->activeTable === 'co_authorship')->toggleable(),
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
                ->modalHeading($this->activeTable === 'sole_authorship' ? 'Submit New Sole Authored Output' : 'Submit New Co-Authored Output')
                ->modalWidth('4xl')
                ->after(fn() => $this->mount()),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewSubmissionFilesAction::make(),
            Tables\Actions\EditAction::make()
                ->form($this->getFormSchema())
                ->modalHeading('Edit Research/Creative Output')
                ->modalWidth('4xl')
                ->visible($this->getActionVisibility()),
            Tables\Actions\DeleteAction::make()
                ->after(fn() => $this->mount())
                ->visible($this->getActionVisibility()),
        ];
    }


    protected function getFormSchema(): array
    {
        $schema = [
            TextInput::make('data.title')
                ->label('Title of Research/Creative Output')
                ->maxLength(255)
                ->required()
                ->columnSpanFull()
                ->live(),

            Select::make('data.output_type')
                ->label('Type of Output')
                ->options([
                    'journal_article' => 'Journal Article',
                    'book_chapter' => 'Book Chapter',
                    'book' => 'Book',
                    'monograph' => 'Monograph/Thesis/Dissertation',
                ])
                ->default('journal_article')
                ->live()
                ->required(),

            TextInput::make('data.journal_name')
                ->label('Journal/Publisher Name')
                ->maxLength(150)
                ->required()
                ->visible(fn(Get $get): bool => $get('data.output_type') !== 'monograph')
                ->live(),

            TextInput::make('data.reviewer')
                ->label('Reviewer or Its Equivalent')
                ->maxLength(150)
                ->required()
                ->visible(fn(Get $get): bool => $get('data.output_type') !== 'journal_article'),

            TextInput::make('data.indexing_body')
                ->label('International Indexing Body')
                ->maxLength(150)
                ->required()
                ->visible(fn(Get $get): bool => $get('data.output_type') === 'journal_article'),

            DatePicker::make('data.date_published')
                ->label('Date Published')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->maxDate(now())
                ->required()
                ->live(),
        ];

        if ($this->activeTable === 'co_authorship') {
            $schema[] = TrimmedIntegerInput::make('data.contribution_percentage')
                ->label('% Contribution')
                ->minValue(1)
                ->maxValue(100)
                ->required();
        }

        $schema[] = Grid::make(3)
            ->columnSpanFull()
            ->schema([
                $this->getKRAFileUploadComponent()->columnSpan(2),

                $this->getAutofillAction(),
            ]);
        return $schema;
    }
}
