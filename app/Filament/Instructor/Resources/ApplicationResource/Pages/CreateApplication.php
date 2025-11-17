<?php

namespace App\Filament\Instructor\Resources\ApplicationResource\Pages;

use App\Filament\Instructor\Resources\ApplicationResource;
use App\Models\PromotionCycle;
use App\Models\Submission;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Section;
use Illuminate\Support\Str;
use App\Filament\Instructor\Pages;

class CreateApplication extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ApplicationResource::class;

    /**
     * Helper: Creates an empty state with a simple link.
     */
    private function createEmptyState(string $pageUrl, string $ctaText, Collection $submissionCollection): Placeholder
    {
        return Placeholder::make(Str::slug($ctaText) . '_empty_state')
            ->label(false)
            ->content(new HtmlString(
                '<div class="flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">You have no unassigned submissions for this criterion.</p>
                    <a href="' . $pageUrl . '" class="inline-flex items-center gap-1 font-medium text-primary-600 hover:text-primary-500 text-sm ring-1 ring-inset ring-primary-500/20 px-3 py-1.5 rounded-lg">
                        ' . $ctaText . '
                    </a>
                </div>'
            ))
            ->hidden($submissionCollection->isNotEmpty());
    }

    /**
     * Helper: Creates an empty state with a link that includes an activeTab.
     */
    private function createEmptyStateWithTab(string $pageUrl, string $tabName, string $ctaText, Collection $submissionCollection): Placeholder
    {
        $url = $pageUrl . '?activeTab=' . $tabName;

        return Placeholder::make(Str::slug($ctaText) . '_empty_state')
            ->label(false)
            ->content(new HtmlString(
                '<div class="flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">You have no unassigned submissions for this criterion.</p>
                    <a href="' . $url . '" class="inline-flex items-center gap-1 font-medium text-primary-600 hover:text-primary-500 text-sm ring-1 ring-inset ring-primary-500/20 px-3 py-1.5 rounded-lg">
                        ' . $ctaText . '
                    </a>
                </div>'
            ))
            ->hidden($submissionCollection->isNotEmpty());
    }

    /**
     * Helper: Creates the CheckboxList for a submission group.
     */
    private function createCheckboxList(Collection $submissions, string $name, string $label): CheckboxList
    {
        return CheckboxList::make($name)
            ->label($label)
            ->options(
                $submissions->mapWithKeys(function (Submission $record) {
                    $title = $record->data['title'] ?? $record->data['name'] ?? $record->data['activity_title'] ?? 'Untitled';
                    $type = Str::of($record->type)->replace('-', ' ')->title();
                    return [$record->id => new HtmlString("<div>{$title}</div><div class='text-xs text-gray-500'>{$type}</div>")];
                })
            )
            ->bulkToggleable()
            ->hidden($submissions->isEmpty());
    }

    protected function getSteps(): array
    {
        $allSubmissions = Submission::where('user_id', Auth::id())
            ->whereNull('application_id')
            ->get();

        // KRA I
        $teSubs = $allSubmissions->whereIn('type', ['te-student-evaluation', 'te-supervisor-evaluation']);
        $imSubs = $allSubmissions->whereIn('type', ['im-sole-authorship', 'im-co-authorship', 'im-academic-program']);
        $mentorSubs = $allSubmissions->whereIn('type', ['mentorship-adviser', 'mentorship-panel', 'mentorship-mentor']);

        // KRA II
        $papersSubs = $allSubmissions->whereIn('type', ['research-sole-authorship', 'research-co-authorship']);
        $translatedSubs = $allSubmissions->whereIn('type', ['research-translated-lead', 'research-translated-contributor']);
        $citationSubs = $allSubmissions->whereIn('type', ['research-citation-local', 'research-citation-international']);
        $patentedSubs = $allSubmissions->whereIn('type', [
            'invention-patent-sole',
            'invention-patent-co-inventor',
            'invention-utility-design-sole',
            'invention-utility-design-co-inventor',
            'invention-commercialized-local',
            'invention-commercialized-international'
        ]);
        $nonPatentedSubs = $allSubmissions->whereIn('type', [
            'invention-software-new-sole',
            'invention-software-new-co',
            'invention-software-updated',
            'invention-plant-animal-sole',
            'invention-plant-animal-co'
        ]);
        $creativePerformingSubs = $allSubmissions->where('type', 'creative-performing-art');
        $creativeExhibitionSubs = $allSubmissions->where('type', 'creative-exhibition');
        $creativeJuriedSubs = $allSubmissions->where('type', 'creative-juried-design');
        $creativeLiterarySubs = $allSubmissions->where('type', 'creative-literary-publication');
        $allCreativeSubs = $creativePerformingSubs->merge($creativeExhibitionSubs)->merge($creativeJuriedSubs)->merge($creativeLiterarySubs);

        // KRA III
        $linkageSubs = $allSubmissions->where('type', 'extension-linkage');
        $incomeSubs = $allSubmissions->where('type', 'extension-income-generation');
        $profServiceSubs = $allSubmissions->whereIn('type', [
            'accreditation_services',
            'judge_examiner',
            'consultant',
            'media_service',
            'training_resource_person'
        ]);
        $socialRespSubs = $allSubmissions->where('type', 'social_responsibility');
        $qualitySubs = $allSubmissions->where('type', 'extension-quality-rating');
        $bonusSubs = $allSubmissions->where('type', 'extension-bonus-designation');

        // KRA IV
        $orgSubs = $allSubmissions->where('type', 'profdev-organization');
        $degreeSubs = $allSubmissions->whereIn('type', ['profdev-doctorate', 'profdev-additional-degree']);
        $trainingSubs = $allSubmissions->where('type', 'profdev-conference-training');
        $presentationSubs = $allSubmissions->where('type', 'profdev-paper-presentation');
        $awardSubs = $allSubmissions->where('type', 'profdev-award-recognition');
        $acadServiceSubs = $allSubmissions->where('type', 'profdev-academic-service');
        $industrySubs = $allSubmissions->where('type', 'profdev-industry-experience');


        return [
            Step::make('Application Details')
                ->description('Select the promotion cycle you are applying for.')
                ->schema([
                    Select::make('evaluation_cycle')
                        ->label('Select Promotion Cycle')
                        ->options(
                            PromotionCycle::where('is_active', true)->pluck('name', 'name')
                        )
                        ->searchable()
                        ->required(),
                ]),
            Step::make('Select Evidence')
                ->description('Select all submissions from your portfolio to include in this application.')
                ->schema([
                    Section::make('KRA I: Instruction')
                        ->collapsible()
                        ->schema([
                            $this->createEmptyState(Pages\KRA1\TeachingEffectivenessPage::getUrl(), 'Add Teaching Effectiveness', $teSubs),
                            $this->createCheckboxList($teSubs, 'submissions.te', 'Teaching Effectiveness'),

                            $this->createEmptyState(Pages\KRA1\InstructionalMaterialsPage::getUrl(), 'Add Instructional Materials', $imSubs),
                            $this->createCheckboxList($imSubs, 'submissions.im', 'Instructional Materials'),

                            $this->createEmptyState(Pages\KRA1\MentorshipServicesPage::getUrl(), 'Add Mentorship Submissions', $mentorSubs),
                            $this->createCheckboxList($mentorSubs, 'submissions.mentor', 'Mentorship'),
                        ]),

                    Section::make('KRA II: Research & Innovation')
                        ->collapsible()
                        ->schema([
                            $this->createEmptyStateWithTab(Pages\KRA2\ResearchOutputsPage::getUrl(), 'published_papers', 'Add Published Papers', $papersSubs),
                            $this->createCheckboxList($papersSubs, 'submissions.papers', 'Published Papers'),

                            $this->createEmptyStateWithTab(Pages\KRA2\ResearchOutputsPage::getUrl(), 'translated_outputs', 'Add Translated Outputs', $translatedSubs),
                            $this->createCheckboxList($translatedSubs, 'submissions.translated', 'Translated Outputs'),

                            $this->createEmptyStateWithTab(Pages\KRA2\ResearchOutputsPage::getUrl(), 'citations', 'Add Citations', $citationSubs),
                            $this->createCheckboxList($citationSubs, 'submissions.citation', 'Citations'),

                            $this->createEmptyStateWithTab(Pages\KRA2\InventionsPage::getUrl(), 'patented_inventions', 'Add Patented Inventions', $patentedSubs),
                            $this->createCheckboxList($patentedSubs, 'submissions.patented', 'Patented Inventions'),

                            $this->createEmptyStateWithTab(Pages\KRA2\InventionsPage::getUrl(), 'non_patentable_inventions', 'Add Non-Patentable Inventions', $nonPatentedSubs),
                            $this->createCheckboxList($nonPatentedSubs, 'submissions.non_patented', 'Non-Patentable Inventions'),

                            $this->createEmptyState(Pages\KRA2\CreativeWorksPage::getUrl(), 'Add Creative Works', $allCreativeSubs),
                            $this->createCheckboxList($creativePerformingSubs, 'submissions.creative_performing', 'Performing Arts'),
                            $this->createCheckboxList($creativeExhibitionSubs, 'submissions.creative_exhibition', 'Exhibitions'),
                            $this->createCheckboxList($creativeJuriedSubs, 'submissions.creative_juried', 'Juried Designs'),
                            $this->createCheckboxList($creativeLiterarySubs, 'submissions.creative_literary', 'Literary Publications'),
                        ]),

                    Section::make('KRA III: Extension')
                        ->collapsible()
                        ->schema([
                            $this->createEmptyStateWithTab(Pages\KRA3\ServiceToInstitutionPage::getUrl(), 'linkages', 'Add Linkages', $linkageSubs),
                            $this->createCheckboxList($linkageSubs, 'submissions.linkage', 'Linkages, Networking and Partnership'),

                            $this->createEmptyStateWithTab(Pages\KRA3\ServiceToInstitutionPage::getUrl(), 'income_generation', 'Add Income Generation', $incomeSubs),
                            $this->createCheckboxList($incomeSubs, 'submissions.income', 'Income Generation'),

                            $this->createEmptyStateWithTab(Pages\KRA3\ServiceToInstitutionPage::getUrl(), 'professional_services', 'Add Professional Services', $profServiceSubs),
                            $this->createCheckboxList($profServiceSubs, 'submissions.prof_service', 'Professional Services'),

                            $this->createEmptyState(Pages\KRA3\ServiceToCommunityPage::getUrl(), 'Add Social Responsibility', $socialRespSubs),
                            $this->createCheckboxList($socialRespSubs, 'submissions.social_resp', 'Social Responsibility'),

                            $this->createEmptyState(Pages\KRA3\QualityOfExtensionPage::getUrl(), 'Add Quality of Extension', $qualitySubs),
                            $this->createCheckboxList($qualitySubs, 'submissions.quality', 'Quality of Extension'),

                            $this->createEmptyState(Pages\KRA3\BonusCriterionPage::getUrl(), 'Add Bonus Criterion', $bonusSubs),
                            $this->createCheckboxList($bonusSubs, 'submissions.bonus_kra3', 'Bonus Criterion'),
                        ]),

                    Section::make('KRA IV: Professional Development')
                        ->collapsible()
                        ->schema([
                            $this->createEmptyState(Pages\KRA4\ProfessionalOrganizationsPage::getUrl(), 'Add Professional Organizations', $orgSubs),
                            $this->createCheckboxList($orgSubs, 'submissions.org', 'Professional Organizations'),

                            $this->createEmptyStateWithTab(Pages\KRA4\ContinuingDevelopmentPage::getUrl(), 'educational_qualifications', 'Add Educational Qualifications', $degreeSubs),
                            $this->createCheckboxList($degreeSubs, 'submissions.degree', 'Educational Qualifications'),

                            $this->createEmptyStateWithTab(Pages\KRA4\ContinuingDevelopmentPage::getUrl(), 'conference_training', 'Add Conference/Training', $trainingSubs),
                            $this->createCheckboxList($trainingSubs, 'submissions.training', 'Conference/Training'),

                            $this->createEmptyStateWithTab(Pages\KRA4\ContinuingDevelopmentPage::getUrl(), 'paper_presentations', 'Add Paper Presentations', $presentationSubs),
                            $this->createCheckboxList($presentationSubs, 'submissions.presentation', 'Paper Presentations'),

                            $this->createEmptyState(Pages\KRA4\AwardsRecognitionPage::getUrl(), 'Add Awards/Recognition', $awardSubs),
                            $this->createCheckboxList($awardSubs, 'submissions.award', 'Awards/Recognition'),

                            $this->createEmptyStateWithTab(Pages\KRA4\BonusIndicatorsPage::getUrl(), 'academic_service', 'Add Academic Service', $acadServiceSubs),
                            $this->createCheckboxList($acadServiceSubs, 'submissions.acad_service', 'Academic Service'),

                            $this->createEmptyStateWithTab(Pages\KRA4\BonusIndicatorsPage::getUrl(), 'industry_experience', 'Add Industry Experience', $industrySubs),
                            $this->createCheckboxList($industrySubs, 'submissions.industry', 'Industry Experience'),
                        ]),
                ]),
            Step::make('Review & Submit')
                ->description('Confirm your submission details.')
                ->schema([
                    Placeholder::make('summary')
                        ->label('Summary of Selected Evidence')
                        ->content(function (Get $get) {
                            $allSubmissionIds = [];
                            $submissionGroups = $get('submissions') ?? [];

                            foreach ($submissionGroups as $groupIds) {
                                if (is_array($groupIds)) {
                                    $allSubmissionIds = array_merge($allSubmissionIds, $groupIds);
                                }
                            }
                            $total = count($allSubmissionIds);

                            return new HtmlString(
                                "You are about to submit a total of <strong>{$total}</strong> pieces of evidence."
                            );
                        }),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        $data['user_id'] = $user->id;
        $data['applicant_current_rank'] = $user->facultyRank?->name ?? 'N/A';
        $data['status'] = 'Draft';

        return $data;
    }

    protected function afterCreate(): void
    {
        $allSubmissionIds = [];
        $submissionGroups = $this->data['submissions'] ?? [];

        foreach ($submissionGroups as $groupIds) {
            if (is_array($groupIds)) {
                $allSubmissionIds = array_merge($allSubmissionIds, $groupIds);
            }
        }

        if (!empty($allSubmissionIds)) {
            Submission::whereIn('id', $allSubmissionIds)
                ->whereNull('application_id')
                ->where('user_id', Auth::id())
                ->update(['application_id' => $this->record->id]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
