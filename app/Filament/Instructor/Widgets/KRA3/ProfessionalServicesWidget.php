<?php

namespace App\Filament\Instructor\Widgets\KRA3;

use App\Models\Application;
use App\Models\Submission;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
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

class ProfessionalServicesWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a3.professional-services-widget';

    public ?string $activeTable = 'accreditation_services';

    public function mount(): void
    {
        parent::mount();
    }

    public function updatedActiveTable(): void
    {
        $this->resetTable();
    }

    public function getGoogleDriveFolderPath(): array
    {
        $kra = $this->getKACategory();
        $baseFolder = 'B: Service to the Community';

        switch ($this->activeTable) {
            case 'accreditation_services':
                return [$kra, $baseFolder, 'QA Services'];
            case 'judge_examiner':
                return [$kra, $baseFolder, 'Judge or Examiner'];
            case 'consultant':
                return [$kra, $baseFolder, 'Consultant'];
            case 'media_service':
                return [$kra, $baseFolder, 'Media Service'];
            case 'training_resource_person':
                return [$kra, $baseFolder, 'Training (Resource Person)'];
            default:
                return [$kra, $baseFolder, Str::slug($this->activeTable)];
        }
    }

    protected function getOptionsMaps(): array
    {
        return [
            'accreditation_scope' => [
                'local' => 'Local',
                'international' => 'International'
            ],
            'award_nature' => [
                'research_award' => 'Research Award',
                'academic_competition' => 'Academic Competition',
            ],
            'consultant_scope' => [
                'local' => 'Local',
                'international' => 'International'
            ],
            'media_service' => [
                'writer_occasional_newspaper' => 'Writer of Occasional Newspaper Column/Magazine Article',
                'writer_regular_newspaper' => 'Writer of Regular Newspaper Column/Magazine Article',
                'host_tv_radio_program' => 'Host of TV/Radio Program',
                'guest_technical_expert' => 'Guesting as Technical Expert for TV or Radio',
            ],
            'training_participation' => [
                'resource_person' => 'Resource Person',
                'convenor' => 'Convenor',
                'facilitator' => 'Facilitator',
                'moderator' => 'Moderator',
                'keynote_speaker' => 'Keynote/Plenary Speaker',
                'panelist' => 'Panelist',
                'other' => 'Other',
            ],
            'training_scope' => [
                'local' => 'Local',
                'international' => 'International'
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        $maps = $this->getOptionsMaps();

        return [
            'Scope' => array_merge($maps['accreditation_scope'], $maps['consultant_scope'], $maps['training_scope']),
            'Award Nature' => $maps['award_nature'],
            'Service' => $maps['media_service'],
            'Participation Type' => $maps['training_participation'],

            'Period Start' => 'm/d/Y',
            'Period End' => 'm/d/Y',
            'Event Date' => 'm/d/Y',
        ];
    }

    private function getAvailableMediaServiceTypes(Get $get): array
    {
        $allTypes = $this->getOptionsMaps()['media_service'];

        $submittedTypes = Submission::where('user_id', Auth::id())
            ->where('application_id', $this->selectedApplicationId)
            ->where('type', $this->getActiveSubmissionType())
            ->pluck('data')
            ->pluck('service')
            ->filter()
            ->all();

        return array_filter(
            $allTypes,
            fn($key) => !in_array($key, $submittedTypes),
            ARRAY_FILTER_USE_KEY
        );
    }

    protected function getKACategory(): string
    {
        return 'KRA III';
    }

    protected function getActiveSubmissionType(): string
    {
        return match ($this->activeTable) {
            'accreditation_services' => 'accreditation_services',
            'judge_examiner' => 'judge_examiner',
            'consultant' => 'consultant',
            'media_service' => 'media_service',
            'training_resource_person' => 'training_resource_person',
            default => 'accreditation_services',
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading(fn(): string => $this->getTableHeading())
            ->columns($this->getTableColumns())
            ->headerActions($this->getTableHeaderActions())
            ->actions($this->getTableActions())
            ->checkIfRecordIsSelectableUsing(
                fn(Submission $record): bool => !$this->submissionExistsForCurrentType() || $record->id === $this->getCurrentSubmissionId()
            );
    }

    protected function getTableQuery(): Builder
    {
        return Submission::query()
            ->where('user_id', Auth::id())
            ->where('type', $this->getActiveSubmissionType())
            ->where('application_id', $this->selectedApplicationId);
    }

    protected function getTableHeading(): string
    {
        return Str::of($this->activeTable)->replace('_', ' ')->title() . ' Submissions';
    }

    protected function getTableColumns(): array
    {
        $maps = $this->getOptionsMaps();
        $columns = [];

        switch ($this->activeTable) {
            case 'accreditation_services':
                $columns = [
                    Tables\Columns\TextColumn::make('data.agency_name')->label('Agency/Organization')->wrap(),
                    Tables\Columns\TextColumn::make('data.services_provided')->label('Services Provided'),
                    Tables\Columns\TextColumn::make('data.scope')
                        ->label('Scope')
                        ->formatStateUsing(fn(?string $state): string => $maps['accreditation_scope'][$state] ?? Str::title($state ?? ''))
                        ->badge(),
                    Tables\Columns\TextColumn::make('data.deployment_count')->label('No. of Days'),
                    ScoreColumn::make('score'),
                ];
                break;
            case 'judge_examiner':
                $columns = [
                    Tables\Columns\TextColumn::make('data.event_title')->label('Title of Event/Activity')->wrap(),
                    Tables\Columns\TextColumn::make('data.organizer')->label('Organizer'),
                    Tables\Columns\TextColumn::make('data.event_date')->label('Date of Event')->date('m/d/Y'),
                    Tables\Columns\TextColumn::make('data.award_nature')
                        ->label('Nature')
                        ->formatStateUsing(fn(?string $state): string => $maps['award_nature'][$state] ?? Str::of($state)->replace('_', ' ')->title())
                        ->badge(),
                    ScoreColumn::make('score'),
                ];
                break;
            case 'consultant':
                $columns = [
                    Tables\Columns\TextColumn::make('data.project_title')->label('Title of Project')->wrap(),
                    Tables\Columns\TextColumn::make('data.organization_name')->label('Organization'),
                    Tables\Columns\TextColumn::make('data.scope')
                        ->label('Scope')
                        ->formatStateUsing(fn(?string $state): string => $maps['consultant_scope'][$state] ?? Str::title($state ?? ''))
                        ->badge(),
                    Tables\Columns\TextColumn::make('data.role')->label('Role'),
                    ScoreColumn::make('score'),
                ];
                break;
            case 'media_service':
                $columns = [
                    Tables\Columns\TextColumn::make('data.service')
                        ->label('Service')
                        ->formatStateUsing(fn(?string $state): string => $maps['media_service'][$state] ?? Str::of($state)->replace('_', ' ')->title())
                        ->badge()
                        ->wrap(),
                    Tables\Columns\TextColumn::make('data.media_name')->label('Name of Media'),
                    Tables\Columns\TextColumn::make('data.program_title')->label('Program Title'),
                    Tables\Columns\TextColumn::make('data.engagement_count')->label('Engagements')
                        ->visible(function ($record): bool {
                            if (!$record || !isset($record->data['service'])) {
                                return false;
                            }
                            return in_array($record->data['service'], ['writer_occasional_newspaper', 'guest_technical_expert']);
                        }),
                    ScoreColumn::make('score'),
                ];
                break;
            case 'training_resource_person':
                $columns = [
                    Tables\Columns\TextColumn::make('data.training_title')->label('Title of Training')->wrap(),
                    Tables\Columns\TextColumn::make('data.participation_type')
                        ->label('Participation')
                        ->formatStateUsing(fn(?string $state): string => $maps['training_participation'][$state] ?? Str::of($state)->replace('_', ' ')->title())
                        ->badge()
                        ->wrap(),
                    Tables\Columns\TextColumn::make('data.organizer')->label('Organizer'),
                    Tables\Columns\TextColumn::make('data.scope')
                        ->label('Scope')
                        ->formatStateUsing(fn(?string $state): string => $maps['training_scope'][$state] ?? Str::title($state ?? ''))
                        ->badge(),
                    Tables\Columns\TextColumn::make('data.total_hours')->label('Total Hours'),
                    ScoreColumn::make('score'),
                ];
                break;
            default:
                return [];
        }
        return $columns;
    }


    protected function getTableHeaderActions(): array
    {
        return [
            CreateAction::make()
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
                ->modalHeading(fn(): string => 'Submit New ' . Str::of($this->activeTable)->replace('_', ' ')->title())
                ->modalWidth('3xl')
                ->hidden(function (Get $get): bool {
                    if ($this->activeTable !== 'media_service') {
                        return false;
                    }
                    return empty($this->getAvailableMediaServiceTypes($get));
                })
                ->after(fn() => $this->mount()),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewSubmissionFilesAction::make(),
            EditAction::make()
                ->form($this->getFormSchema())
                ->modalHeading(fn(): string => 'Edit ' . Str::of($this->activeTable)->replace('_', ' ')->title())
                ->modalWidth('3xl')
                ->visible($this->getActionVisibility()),
            DeleteAction::make()
                ->after(fn() => $this->mount())
                ->visible($this->getActionVisibility()),
        ];
    }

    protected function getFormSchema(): array
    {
        $schema = match ($this->activeTable) {
            'accreditation_services' => $this->getAccreditationFormSchema(),
            'judge_examiner' => $this->getJudgeExaminerFormSchema(),
            'consultant' => $this->getConsultantFormSchema(),
            'media_service' => $this->getMediaServiceFormSchema(),
            'training_resource_person' => $this->getTrainingResourcePersonFormSchema(),
            default => [],
        };

        $schema[] = $this->getKRAFileUploadComponent();

        return $schema;
    }

    protected function getAccreditationFormSchema(): array
    {
        return [
            TextInput::make('data.agency_name')->label('Name of Agency/Organization')->required()->maxLength(255)->columnSpanFull(),
            DatePicker::make('data.period_start')
                ->label('Appointment Period Start')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now())
                ->live(),
            DatePicker::make('data.period_end')
                ->label('Appointment Period End')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->minDate(fn(Get $get) => $get('data.period_start')),
            Textarea::make('data.services_provided')->label('QA-related Services Provided')->required()->maxLength(65535)->columnSpanFull(),
            Select::make('data.scope')->label('Scope')
                ->options($this->getOptionsMaps()['accreditation_scope'])
                ->searchable()
                ->required(),
            TrimmedIntegerInput::make('data.deployment_count')->label('No. of Days Rendered')->required()->minValue(1),
        ];
    }

    protected function getJudgeExaminerFormSchema(): array
    {
        return [
            Textarea::make('data.event_title')->label('Title of the Event/Activity')->required()->maxLength(65535)->columnSpanFull(),
            TextInput::make('data.organizer')->label('Organizer/Sponsor')->required()->maxLength(255),
            DatePicker::make('data.event_date')
                ->label('Date of Event')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now()),
            Select::make('data.award_nature')->label('Nature of the Award')
                ->options($this->getOptionsMaps()['award_nature'])
                ->searchable()
                ->required(),
            TextInput::make('data.venue')->label('Venue')->required()->maxLength(255),
            TextInput::make('data.role')->label('Role (e.g., Judge, Examiner)')->required()->maxLength(255),
        ];
    }

    protected function getConsultantFormSchema(): array
    {
        return [
            Textarea::make('data.project_title')->label('Title of the Project/Consultancy')->required()->maxLength(65535)->columnSpanFull(),
            TextInput::make('data.organization_name')->label('Name of Organization/Sponsoring Body')->required()->maxLength(255)->columnSpanFull(),
            DatePicker::make('data.period_start')
                ->label('Period of Engagement Start')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now())
                ->live(),
            DatePicker::make('data.period_end')
                ->label('Period of Engagement End')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->minDate(fn(Get $get) => $get('data.period_start')),
            Select::make('data.scope')->label('Scope')
                ->options($this->getOptionsMaps()['consultant_scope'])
                ->searchable()
                ->required(),
            TextInput::make('data.role')->label('Role')->required()->maxLength(255),
        ];
    }

    protected function getMediaServiceFormSchema(): array
    {
        return [
            Select::make('data.service')->label('Service Rendered')
                ->options(function (?Submission $record, Get $get): array {
                    $allTypes = $this->getOptionsMaps()['media_service'];
                    if ($record) {
                        return $allTypes;
                    }
                    return $this->getAvailableMediaServiceTypes($get);
                })
                ->searchable()
                ->required()
                ->live()
                ->reactive()
                ->columnSpanFull(),
            TextInput::make('data.media_name')->label('Name of Media (Newspaper/Magazine/Station/Network)')->required()->maxLength(255),
            TextInput::make('data.program_title')->label('Title of Newspaper Column or TV/Radio Program')->required()->maxLength(255),
            DatePicker::make('data.period_start')
                ->label('Date/Period Started')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now())
                ->live(),
            DatePicker::make('data.period_end')
                ->label('Date/Period Ended')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->minDate(fn(Get $get) => $get('data.period_start'))
                ->visible(fn(Get $get): bool => $get('data.service') === 'writer_regular_newspaper' || $get('data.service') === 'host_tv_radio_program'),
            TrimmedIntegerInput::make('data.engagement_count')->label('No. of Engagements/Guestings')
                ->required()
                ->minValue(1)
                ->visible(fn(Get $get): bool => $get('data.service') === 'writer_occasional_newspaper' || $get('data.service') === 'guest_technical_expert'),
        ];
    }

    protected function getTrainingResourcePersonFormSchema(): array
    {
        return [
            Textarea::make('data.training_title')->label('Title of the Training/Course/Seminar/Workshop')->required()->maxLength(65535)->columnSpanFull(),
            Select::make('data.participation_type')->label('Type of Participation/Role')
                ->options($this->getOptionsMaps()['training_participation'])
                ->searchable()
                ->required(),
            TextInput::make('data.organizer')->label('Organizer/Sponsoring Body')->required()->maxLength(255),
            DatePicker::make('data.period_start')
                ->label('Date Conducted/Start Date')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now())
                ->live(),
            DatePicker::make('data.period_end')
                ->label('End Date (if applicable)')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->minDate(fn(Get $get) => $get('data.period_start')),
            Select::make('data.scope')->label('Scope')
                ->options($this->getOptionsMaps()['training_scope'])
                ->searchable()
                ->required(),
            TrimmedIntegerInput::make('data.total_hours')->label('Total No. of Hours')->required()->minValue(1),
        ];
    }
}
