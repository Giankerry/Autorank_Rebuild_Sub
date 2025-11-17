<?php

namespace App\Filament\Validator\Resources\ApplicationResource\Pages;

use App\Filament\Validator\Resources\ApplicationResource;
use App\Models\Application;
use App\Services\ApplicationScoringService;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use App\Filament\Instructor\Widgets\KRA1;
use App\Filament\Instructor\Widgets\KRA2;
use App\Filament\Instructor\Widgets\KRA3;
use App\Filament\Instructor\Widgets\KRA4;

class ValidateApplication extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = ApplicationResource::class;

    protected static string $view = 'filament.validator.pages.validate-application';

    public ?Application $record = null;
    public ?array $data = [];

    public ?string $activeTab = 'kra1';

    public ?string $activeKra1Tab = 'criterion_a';
    public ?string $activeKra2Tab = 'criterion_a';
    public ?string $activeKra3Tab = 'criterion_a';
    public ?string $activeKra4Tab = 'criterion_a';

    public function getTitle(): string | Htmlable
    {
        return "Validate Application: " . $this->record->user->name;
    }

    public function mount(Application $record): void
    {
        $this->record = $record;
        $this->form->fill($this->record->toArray());

        $this->activeKra1Tab = array_key_first($this->getKra1Widgets());
        $this->activeKra2Tab = array_key_first($this->getKra2Widgets());
        $this->activeKra3Tab = array_key_first($this->getKra3Widgets());
        $this->activeKra4Tab = array_key_first($this->getKra4Widgets());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('remarks')
                    ->label('Overall Validator Remarks')
                    ->placeholder('Add overall remarks for the entire application..')
                    ->rows(10),
            ])
            ->model($this->record)
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('validate')
                ->label('Mark as Validated')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->action(function (ApplicationScoringService $scoringService) {
                    $this->record->update($this->form->getState());
                    $scoringService->calculateScore($this->record);
                    $this->record->status = 'Validated';
                    $this->record->save();

                    Notification::make()
                        ->success()
                        ->title('Application Validated')
                        ->body('The application has been successfully marked as validated.')
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('reject')
                ->label('Reject Application')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->modalHeading('Reject Application')
                ->modalDescription('Are you sure you want to reject this application? This action cannot be undone.')
                ->action(function () {
                    $this->record->update($this->form->getState());
                    $this->record->status = 'Rejected';
                    $this->record->save();

                    Notification::make()
                        ->success()
                        ->title('Application Rejected')
                        ->body('The application has been successfully marked as rejected.')
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }

    public function getKra1Widgets(): array
    {
        return [
            'Criterion A: Teaching Effectiveness' => [
                KRA1\TeachingEffectivenessWidget::class,
            ],
            'Criterion B: Instructional Materials' => [
                KRA1\InstructionalMaterialsWidget::class,
            ],
            'Criterion C: Mentorship Services' => [
                KRA1\MentorshipServicesWidget::class,
            ],
        ];
    }

    public function getKra2Widgets(): array
    {
        return [
            'Criterion A: Research Outputs' => [
                KRA2\PublishedPapersWidget::class,
                KRA2\CitationsWidget::class,
                KRA2\TranslatedOutputsWidget::class,
            ],
            'Criterion B: Inventions' => [
                KRA2\PatentedInventionsWidget::class,
                KRA2\NonPatentableInventionsWidget::class,
            ],
            'Criterion C: Creative Works' => [
                KRA2\LiteraryPublicationWidget::class,
                KRA2\PerformingArtWidget::class,
                KRA2\JuriedDesignWidget::class,
                KRA2\ExhibitionWidget::class,
            ],
        ];
    }

    public function getKra3Widgets(): array
    {
        return [
            'Criterion A: Service to Institution' => [
                KRA3\SocialResponsibilityWidget::class,
            ],
            'Criterion B: Quality of Extension' => [
                KRA3\QualityOfExtensionWidget::class,
            ],
            'Criterion C: Linkages' => [
                KRA3\LinkagesWidget::class,
            ],
            'Criterion D: Professional Services' => [
                KRA3\ProfessionalServicesWidget::class,
            ],
            'Criterion E: Income Generation' => [
                KRA3\IncomeGenerationWidget::class,
            ],
            'Criterion F: Bonus Criterion' => [
                KRA3\BonusCriterionWidget::class,
            ],
        ];
    }

    public function getKra4Widgets(): array
    {
        return [
            'Criterion A: Professional Organizations' => [
                KRA4\ProfessionalOrganizationsWidget::class,
            ],
            'Criterion B: Conference & Training' => [
                KRA4\ConferenceTrainingWidget::class,
                KRA4\PaperPresentationsWidget::class,
            ],
            'Criterion C: Awards & Recognition' => [
                KRA4\AwardsRecognitionWidget::class,
            ],
            'Criterion D: Bonus Indicators' => [
                KRA4\EducationalQualificationsWidget::class,
                KRA4\AcademicServiceWidget::class,
                KRA4\IndustryExperienceWidget::class,
            ],
        ];
    }

    public function getWidgets(): array
    {
        return [];
    }

    public function getWidgetData(): array
    {
        return [
            'record' => $this->record,
            'validation_mode' => true,
        ];
    }
}
