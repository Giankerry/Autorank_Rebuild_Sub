<?php

namespace App\Observers;

use App\Models\Submission;
use App\Services\ScoringService;
use App\Services\GoogleDriveService;
use App\Services\ApplicationScoringService;
use Illuminate\Support\Facades\Log;

class SubmissionObserver
{
    protected $scoringService;
    protected $driveService;
    protected $applicationScoringService;

    public function __construct(ScoringService $scoringService, GoogleDriveService $driveService, ApplicationScoringService $applicationScoringService)
    {
        $this->scoringService = $scoringService;
        $this->driveService = $driveService;
        $this->applicationScoringService = $applicationScoringService;
    }

    /**
     * Handle the Submission "saving" event.
     * This calculates the individual score AND sets the KRA category.
     */
    public function saving(Submission $submission): void
    {
        $submission->category = $this->getCategoryForType($submission->type);

        $calculatedScores = match ($submission->type) {

            // KRA I, Criterion A
            'te-student-evaluation' => $this->scoringService
                ->calculateTeachingEffectivenessStudentScore($submission->data, $submission->type),

            'te-supervisor-evaluation' => $this->scoringService
                ->calculateTeachingEffectivenessSupervisorScore($submission->data, $submission->type),

            // KRA I, Criterion B
            'im-sole-authorship' => $this->scoringService
                ->calculateInstructionalMaterialSoleScore($submission->data, $submission->type),

            'im-co-authorship' => $this->scoringService
                ->calculateInstructionalMaterialCoAuthorScore($submission->data, $submission->type),

            'im-academic-program' => $this->scoringService
                ->calculateAcademicProgramScore($submission->data, $submission->type),

            // KRA I, Criterion C
            'mentorship-adviser' => $this->scoringService
                ->calculateMentorshipAdviserScore($submission->data, $submission->type),

            'mentorship-panel' => $this->scoringService
                ->calculateMentorshipPanelScore($submission->data, $submission->type),

            'mentorship-mentor' => $this->scoringService
                ->calculateMentorshipMentorScore($submission->data, $submission->type),

            // KRA II, Criterion A (Published Outputs)
            'research-sole-authorship' => $this->scoringService
                ->calculateResearchSoleScore($submission->data, $submission->type),

            'research-co-authorship' => $this->scoringService
                ->calculateResearchCoAuthorScore($submission->data, $submission->type),

            // KRA II, Criterion A (Translated Outputs)
            'research-translated-lead' => $this->scoringService
                ->calculateTranslatedOutputLeadScore($submission->data, $submission->type),

            'research-translated-contributor' => $this->scoringService
                ->calculateTranslatedOutputContributorScore($submission->data, $submission->type),

            // KRA II, Criterion A (Citations)
            'research-citation-local' => $this->scoringService
                ->calculateResearchCitationLocalScore($submission->data, $submission->type),

            'research-citation-international' => $this->scoringService
                ->calculateResearchCitationInternationalScore($submission->data, $submission->type),

            // KRA II, Criterion B (Patented Inventions)
            'invention-patent-sole' => $this->scoringService
                ->calculateInventionPatentSoleScore($submission->data, $submission->type),

            'invention-patent-co-inventor' => $this->scoringService
                ->calculateInventionPatentCoInventorScore($submission->data, $submission->type),

            // KRA II, Criterion B (Utility/Design)
            'invention-utility-design-sole' => $this->scoringService
                ->calculateUtilityDesignSoleScore($submission->data, $submission->type),

            'invention-utility-design-co-inventor' => $this->scoringService
                ->calculateUtilityDesignCoInventorScore($submission->data, $submission->type),

            // KRA II, Criterion B (Commercialized)
            'invention-commercialized-local' => $this->scoringService
                ->calculateCommercializedLocalScore($submission->data, $submission->type),

            'invention-commercialized-international' => $this->scoringService
                ->calculateCommercializedInternationalScore($submission->data, $submission->type),

            // KRA II, Criterion B (Non-Patentable: Software)
            'invention-software-new-sole' => $this->scoringService
                ->calculateSoftwareNewSoleScore($submission->data, $submission->type),

            'invention-software-new-co' => $this->scoringService
                ->calculateSoftwareNewCoDeveloperScore($submission->data, $submission->type),

            'invention-software-updated' => $this->scoringService
                ->calculateSoftwareUpdatedScore($submission->data, $submission->type),

            // KRA II, Criterion B (Non-Patentable: Plant/Animal/Microbe)
            'invention-plant-animal-sole' => $this->scoringService
                ->calculatePlantAnimalSoleScore($submission->data, $submission->type),

            'invention-plant-animal-co' => $this->scoringService
                ->calculatePlantAnimalCoDeveloperScore($submission->data, $submission->type),

            // KRA II, Criterion C
            'creative-performing-art' => $this->scoringService
                ->calculateCreativePerformingArtScore($submission->data, $submission->type),

            'creative-exhibition' => $this->scoringService
                ->calculateCreativeExhibitionScore($submission->data, $submission->type),

            'creative-juried-design' => $this->scoringService
                ->calculateCreativeJuriedDesignScore($submission->data, $submission->type),

            'creative-literary-publication' => $this->scoringService
                ->calculateCreativeLiteraryPublicationScore($submission->data, $submission->type),

            // KRA III, Criterion A
            'extension-linkage' => $this->scoringService
                ->calculateExtensionLinkageScore($submission->data, $submission->type),

            'extension-income-generation' => $this->scoringService
                ->calculateExtensionIncomeGenerationScore($submission->data, $submission->type),

            // KRA III, Criterion B
            'accreditation_services' => $this->scoringService
                ->calculateAccreditationServiceScore($submission->data, $submission->type),

            'judge_examiner' => $this->scoringService
                ->calculateJudgeExaminerScore($submission->data, $submission->type),

            'consultant' => $this->scoringService
                ->calculateConsultantScore($submission->data, $submission->type),

            'media_service' => $this->scoringService
                ->calculateMediaServiceScore($submission->data, $submission->type),

            'training_resource_person' => $this->scoringService
                ->calculateTrainingResourcePersonScore($submission->data, $submission->type),

            'social_responsibility' => $this->scoringService
                ->calculateSocialResponsibilityScore($submission->data, $submission->type),

            // KRA III, Criterion C
            'extension-quality-rating' => $this->scoringService
                ->calculateQualityOfExtensionScore($submission->data, $submission->type),

            // KRA III, Criterion D
            'extension-bonus-designation' => $this->scoringService
                ->calculateBonusDesignationScore($submission->data, $submission->type),

            // KRA IV, Criterion A
            'profdev-organization' => $this->scoringService
                ->calculateProfOrgScore($submission->data, $submission->type),

            // KRA IV, Criterion B
            'profdev-doctorate' => $this->scoringService
                ->calculateDoctorateDegreeScore($submission->data, $submission->type),

            'profdev-additional-degree' => $this->scoringService
                ->calculateAdditionalDegreeScore($submission->data, $submission->type),

            'profdev-conference-training' => $this->scoringService
                ->calculateConferenceTrainingScore($submission->data, $submission->type),

            'profdev-paper-presentation' => $this->scoringService
                ->calculatePaperPresentationScore($submission->data, $submission->type),

            // KRA IV, Criterion C
            'profdev-award-recognition' => $this->scoringService
                ->calculateAwardRecognitionScore($submission->data, $submission->type),

            // KRA IV, Criterion D
            'profdev-academic-service' => $this->scoringService
                ->calculateAcademicServiceScore($submission->data, $submission->type),
            'profdev-industry-experience' => $this->scoringService
                ->calculateIndustryExperienceScore($submission->data, $submission->type),

            default => ['raw' => 0.0, 'score' => 0.0],
        };

        $submission->raw_score = $calculatedScores['raw'];
        $submission->score = $calculatedScores['score'];
    }

    public function saved(Submission $submission): void
    {
        if ($submission->application_id) {
            $this->applicationScoringService->calculateScore($submission->application);
        }
    }

    public function deleted(Submission $submission): void
    {
        if ($submission->application_id) {
            $this->applicationScoringService->calculateScore($submission->application);
        }

        $this->cleanupGoogleDriveFiles($submission);
    }

    public function deleting(Submission $submission): void
    {
        $this->cleanupGoogleDriveFiles($submission);
    }

    private function cleanupGoogleDriveFiles(Submission $submission): void
    {
        $fileIds = $submission->google_drive_file_id;

        if (!is_array($fileIds) || empty($fileIds)) {
            return;
        }

        try {
            $driveService = $this->driveService->forUser($submission->user);

            foreach ($fileIds as $fileId) {
                if (is_string($fileId)) {
                    $driveService->deleteFile($fileId);
                }
            }
        } catch (\Exception $e) {
            Log::error('Google Drive file deletion failed during SubmissionObserver cleanup.', [
                'submission_id' => $submission->id,
                'user_id' => $submission->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getCategoryForType(string $type): ?string
    {
        return match ($type) {
            // KRA I
            'te-student-evaluation',
            'te-supervisor-evaluation',
            'im-sole-authorship',
            'im-co-authorship',
            'im-academic-program',
            'mentorship-adviser',
            'mentorship-panel',
            'mentorship-mentor'
            => 'kra_1',

            // KRA II
            'research-sole-authorship',
            'research-co-authorship',
            'research-translated-lead',
            'research-translated-contributor',
            'research-citation-local',
            'research-citation-international',
            'invention-patent-sole',
            'invention-patent-co-inventor',
            'invention-utility-design-sole',
            'invention-utility-design-co-inventor',
            'invention-commercialized-local',
            'invention-commercialized-international',
            'invention-software-new-sole',
            'invention-software-new-co',
            'invention-software-updated',
            'invention-plant-animal-sole',
            'invention-plant-animal-co',
            'creative-performing-art',
            'creative-exhibition',
            'creative-juried-design',
            'creative-literary-publication'
            => 'kra_2',

            // KRA III
            'extension-linkage',
            'extension-income-generation',
            'accreditation_services',
            'judge_examiner',
            'consultant',
            'media_service',
            'training_resource_person',
            'social_responsibility',
            'extension-quality-rating',
            'extension-bonus-designation'
            => 'kra_3',

            // KRA IV
            'profdev-organization',
            'profdev-doctorate',
            'profdev-additional-degree',
            'profdev-conference-training',
            'profdev-paper-presentation',
            'profdev-award-recognition',
            'profdev-academic-service',
            'profdev-industry-experience'
            => 'kra_4',

            // Default
            default => null,
        };
    }
}
