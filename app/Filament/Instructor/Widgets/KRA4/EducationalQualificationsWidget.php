<?php

namespace App\Filament\Instructor\Widgets\KRA4;

use App\Models\Application;
use App\Models\Submission;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

use App\Filament\Traits\AutofillDocument; //import for Autofill
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Components\FileUpload;

class EducationalQualificationsWidget extends BaseKRAWidget
{
    // NEW: Use both traits
    use HandlesKRAFileUploads;
    use AutofillDocument;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a4.educational-qualifications-widget';

    public ?string $activeTable = 'doctorate_degree';

    public function updatedActiveTable(): void
    {
        $this->resetTable();
    }

    public function getGoogleDriveFolderPath(): array
    {
        $kra = $this->getKACategory();
        $baseFolder = 'B: Educational Qualifications';

        switch ($this->activeTable) {
            case 'doctorate_degree':
                return [$kra, $baseFolder, 'Doctorate'];
            case 'masters_degree':
                return [$kra, $baseFolder, 'Masters'];
            case 'diploma_certificate':
                return [$kra, $baseFolder, 'Diploma and Certificate'];
        }

        return [$kra, $baseFolder];
    }

    protected function getKACategory(): string
    {
        return 'KRA IV';
    }

    protected function getActiveSubmissionType(): string
    {
        return 'profdev-' . $this->activeTable;
    }

    protected function getOptionsMaps(): array
    {
        return [
            'degree_type' => [
                'doctorate' => 'Doctorate',
                'masters' => 'Master\'s',
                'diploma' => 'Diploma/Certificate',
            ],
            'output_type' => [
                'degree' => 'Degree',
                'certificate' => 'Certificate'
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        return [
            'Type' => $this->getOptionsMaps()['degree_type'],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading('Educational Qualifications')
            ->columns([
                Tables\Columns\TextColumn::make('data.degree_type')
                    ->label('Type')
                    ->formatStateUsing(fn(?string $state): string => $this->getOptionsMaps()['degree_type'][$state] ?? Str::title($state ?? ''))
                    ->badge(),
                Tables\Columns\TextColumn::make('data.name')->label('Name of Degree/Diploma/Certificate')->wrap(),
                Tables\Columns\TextColumn::make('data.institution')->label('Name of HEI'),
                Tables\Columns\TextColumn::make('data.date_completed')->label('Date Completed')->date('m/d/Y'),
                Tables\Columns\IconColumn::make('data.is_qualified')
                    ->label('Used for Promotion')
                    ->boolean()
                    ->visible($this->activeTable === 'doctorate_degree' || $this->activeTable === 'masters_degree'),
                ScoreColumn::make('score'),
            ])
            ->headerActions($this->getTableHeaderActions())
            ->actions($this->getTableActions());
    }

    protected function getTableQuery(): Builder
    {
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
                    return $application->status !== 'draft';
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['user_id'] = Auth::id();
                    $data['application_id'] = $this->selectedApplicationId;
                    $data['category'] = $this->getKACategory();
                    $data['type'] = $this->getActiveSubmissionType();
                    return $data;
                })
                ->modalHeading('Submit New Educational Qualification')
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
                ->modalHeading('Edit Educational Qualification')
                ->modalWidth('3xl')
                ->visible($this->getActionVisibility()),
            Tables\Actions\DeleteAction::make()
                ->after(fn() => $this->mount())
                ->visible($this->getActionVisibility()),
        ];
    }

    //Autofill Implementation for Educational Qualifications
    protected function mapCertificateDataToForm(Set $set, Get $get, ?string $credentialType, ?string $dateCompleted, ?string $issuingOrg, ?string $venue): void
    {
        $set('data.name', $credentialType ?? $get('data.name'));
        $set('data.institution', $issuingOrg ?? $get('data.institution'));
        $set('data.date_completed', $dateCompleted ?? $get('data.date_completed'));
        $extractedType = Str::lower($credentialType ?? '');

        if (Str::contains($extractedType, ['master', 'm.s.'])) {
            $set('data.degree_type', 'masters');
        } elseif (Str::contains($extractedType, ['doctorate', 'ph.d.'])) {
            $set('data.degree_type', 'doctorate');
        } elseif (Str::contains($extractedType, ['diploma', 'certificate'])) {
            $set('data.degree_type', 'diploma'); // Map to the general diploma category
        }
    }

    protected function mapMoaDataToForm(Set $set, Get $get, ?string $partnerName, ?string $startDate, ?string $expirationDate, ?string $scope): void
    {
        Notification::make()->title('Document Type Mismatch')->body('The uploaded document is a MOA. This form requires a Diploma or Certificate.')->warning()->send();
    }

    protected function mapResearchDataToForm(Set $set, Get $get, ?string $title, ?string $authorList, ?string $publisher, ?string $datePublished, ?string $documentType): void
    {
        Notification::make()->title('Document Type Mismatch')->body('The uploaded document is a Research Paper/Thesis. This form requires a Diploma or Certificate.')->warning()->send();
    }


    protected function getFormSchema(): array
    {
        if ($this->activeTable === 'doctorate_degree' || $this->activeTable === 'masters_degree') {
            $schema = [
                Select::make('data.degree_type')
                    ->label('Type')
                    ->options($this->getOptionsMaps()['degree_type'])
                    ->required()
                    ->searchable()
                    ->live(),
                TextInput::make('data.name')
                    ->label('Name of Degree')
                    ->required()
                    ->maxLength(255)
                    ->live(),
                TextInput::make('data.institution')
                    ->label('Name of HEI')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->live(),
                DatePicker::make('data.date_completed')
                    ->label('Date Completed')
                    ->native(false)
                    ->displayFormat('m/d/Y')
                    ->required()
                    ->maxDate(now())
                    ->live(),
                Toggle::make('data.is_qualified')
                    ->label('Is this degree being used for automatic 1 sub-rank increase?')
                    ->helperText('Check this ONLY if you are using this degree for automatic promotion instead of points in this evaluation.')
                    ->inline(false)
                    ->default(false),
            ];
        } else {
            // Diploma/Certificate form section
            $schema = [
                Select::make('data.degree_type')
                    ->label('Type')
                    ->options($this->getOptionsMaps()['degree_type'])
                    ->required()
                    ->searchable()
                    ->live(),
                TextInput::make('data.name')
                    ->label('Name of Degree/Diploma/Certificate')
                    ->required()
                    ->maxLength(255)
                    ->live(),
                TextInput::make('data.institution')
                    ->label('Name of HEI')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->live(),
                DatePicker::make('data.date_completed')
                    ->label('Date Completed')
                    ->native(false)
                    ->displayFormat('m/d/Y')
                    ->required()
                    ->maxDate(now())
                    ->live(),
            ];
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
