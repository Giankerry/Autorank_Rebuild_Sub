<?php

namespace App\Services;

use App\Models\Setting;

class ScoringService
{
    private array $caps = [];

    public function __construct()
    {
        $this->caps = Setting::all()->pluck('value', 'key')->all();
    }

    private function getCap(string $key): float
    {
        return (float)($this->caps[$key] ?? 0);
    }

    /**
     * Maps a submission type to its corresponding score cap key.
     */
    private function getCapKeyForType(string $type): ?string
    {
        return match ($type) {
            // KRA I
            'te-student-evaluation',
            'te-supervisor-evaluation'
            => 'kra_1_a_cap', // 60

            'im-sole-authorship',
            'im-co-authorship',
            'im-academic-program'
            => 'kra_1_b_cap', // 30

            'mentorship-adviser',
            'mentorship-panel',
            'mentorship-mentor'
            => 'kra_1_c_cap', // 10

            // KRA II
            'research-sole-authorship',
            'research-co-authorship'
            => 'kra_2_a_cap', // 100
            'research-citation-local'
            => 'kra_2_a_3_1_cap', // 40
            'research-citation-international'
            => 'kra_2_a_3_2_cap', // 60

            'invention-patent-sole',
            'invention-patent-co-inventor',
            'invention-utility-design-sole',
            'invention-utility-design-co-inventor',
            'invention-software-new-sole',
            'invention-software-new-co',
            'invention-software-updated',
            'invention-plant-animal-sole',
            'invention-plant-animal-co'
            => 'kra_2_b_cap', // 100
            'r-and-d-patented-local' // This is an assumed type
            => 'kra_2_b_1_2_1_cap', // 20
            'r-and-d-patented-international' // This is an assumed type
            => 'kra_2_b_1_2_2_cap', // 30

            'creative-performing-art',
            'creative-exhibition',
            'creative-juried-design',
            'creative-literary-publication'
            => 'kra_2_c_cap', // 100

            // KRA III
            'extension-linkage',
            'extension-income-generation'
            => 'kra_3_a_cap', // 30

            'accreditation_services',
            'judge_examiner',
            'consultant',
            'media_service',
            'training_resource_person',
            'social_responsibility'
            => 'kra_3_b_cap', // 50

            'extension-quality-rating'
            => 'kra_3_c_cap', // 20

            'extension-bonus-designation'
            => 'kra_3_d_cap', // 20

            // KRA IV
            'profdev-organization'
            => 'kra_4_a_cap', // 20

            'profdev-doctorate',
            'profdev-additional-degree',
            'profdev-conference-training',
            'profdev-paper-presentation'
            => 'kra_4_b_cap', // 60
            'training-participation' // This is an assumed type
            => 'kra_4_b_2_cap', // 10
            'training-paper-presentation' // This is an assumed type
            => 'kra_4_b_3_cap', // 10

            'profdev-award-recognition'
            => 'kra_4_c_cap', // 20
            'profdev-academic-service',
            'profdev-industry-experience'
            => 'kra_4_d_cap', // 20

            default => null,
        };
    }

    /**
     * Applies the correct cap to a raw score based on submission type.
     */
    private function applyCap(float $rawScore, string $type): array
    {
        $capKey = $this->getCapKeyForType($type);

        if (is_null($capKey)) {
            // No cap defined for this type
            return ['raw' => $rawScore, 'score' => $rawScore];
        }

        $cap = $this->getCap($capKey);
        $score = min($rawScore, $cap);

        return ['raw' => $rawScore, 'score' => $score];
    }

    // KRA I, Criterion A
    public function calculateTeachingEffectivenessStudentScore(array $data, string $type): array
    {
        $average = $this->calculateSpecialAverage(
            $this->getRatingFieldKeys('student'),
            $data,
            (int)($data['student_deducted_semesters'] ?? 0),
            $data['student_deduction_reason'] ?? 'NOT APPLICABLE'
        );
        $cappedAverage = min($average, 100.0);

        $rawScore = $average;
        $score = $cappedAverage * ((float)$this->getCap('kra_1_a_cap') * 0.60) / 100.0;

        return ['raw' => $rawScore, 'score' => $score];
    }

    public function calculateTeachingEffectivenessSupervisorScore(array $data, string $type): array
    {
        $average = $this->calculateSpecialAverage(
            $this->getRatingFieldKeys('supervisor'),
            $data,
            (int)($data['supervisor_deducted_semesters'] ?? 0),
            $data['supervisor_deduction_reason'] ?? 'NOT APPLICABLE'
        );
        $cappedAverage = min($average, 100.0);

        $rawScore = $average;
        $score = $cappedAverage * ((float)$this->getCap('kra_1_a_cap') * 0.40) / 100.0;

        return ['raw' => $rawScore, 'score' => $score];
    }

    // KRA I, Criterion B
    public function calculateInstructionalMaterialSoleScore(array $data, string $type): array
    {
        $materialType = $data['material_type'] ?? null;
        if (!$materialType) return ['raw' => 0.0, 'score' => 0.0];

        $rawScore = $this->getInstructionalMaterialBaseScore($materialType);
        return $this->applyCap($rawScore, $type);
    }

    public function calculateInstructionalMaterialCoAuthorScore(array $data, string $type): array
    {
        $materialType = $data['material_type'] ?? null;
        $percentage = (float)($data['contribution_percentage'] ?? 0);
        if (!$materialType || $percentage <= 0) return ['raw' => 0.0, 'score' => 0.0];

        $baseScore = $this->getInstructionalMaterialBaseScore($materialType);
        $rawScore = $baseScore * ($percentage / 100.0);
        return $this->applyCap($rawScore, $type);
    }

    public function calculateAcademicProgramScore(array $data, string $type): array
    {
        $role = $data['role'] ?? null;
        if (!$role) return ['raw' => 0.0, 'score' => 0.0];

        $rawScore = match ($role) {
            'Lead' => 10.0,
            'Contributor' => 5.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    // KRA I, Criterion C
    public function calculateMentorshipAdviserScore(array $data, string $type): array
    {
        $mType = $data['mentorship_type'] ?? null;
        if (!$mType) return ['raw' => 0.0, 'score' => 0.0];

        $totalCount = $this->getMentorshipAcademicYearTotal($data);
        $multiplier = match ($mType) {
            'special_capstone_project' => 3.0,
            'undergrad_thesis' => 5.0,
            'masters_thesis' => 8.0,
            'dissertation' => 10.0,
            default => 0.0,
        };
        $rawScore = (float)$totalCount * $multiplier;
        return $this->applyCap($rawScore, $type);
    }

    public function calculateMentorshipPanelScore(array $data, string $type): array
    {
        $mType = $data['mentorship_type'] ?? null;
        if (!$mType) return ['raw' => 0.0, 'score' => 0.0];

        $totalCount = $this->getMentorshipAcademicYearTotal($data);
        $multiplier = match ($mType) {
            'special_capstone_project' => 1.0,
            'undergrad_thesis' => 1.0,
            'masters_thesis' => 2.0,
            'dissertation' => 2.0,
            default => 0.0,
        };
        $rawScore = (float)$totalCount * $multiplier;
        return $this->applyCap($rawScore, $type);
    }

    public function calculateMentorshipMentorScore(array $data, string $type): array
    {
        $dateAwarded = $data['date_awarded'] ?? null;
        $rawScore = !empty($dateAwarded) ? 3.0 : 0.0;
        return $this->applyCap($rawScore, $type);
    }

    // KRA II, Criterion A: (Published Papers)
    public function calculateResearchSoleScore(array $data, string $type): array
    {
        $outputType = $data['output_type'] ?? null;
        if (!$outputType) return ['raw' => 0.0, 'score' => 0.0];

        $rawScore = $this->getResearchBaseScore($outputType);
        return $this->applyCap($rawScore, $type);
    }

    public function calculateResearchCoAuthorScore(array $data, string $type): array
    {
        $outputType = $data['output_type'] ?? null;
        $percentage = (float)($data['contribution_percentage'] ?? 0);
        if (!$outputType || $percentage <= 0) return ['raw' => 0.0, 'score' => 0.0];

        $baseScore = $this->getResearchBaseScore($outputType);
        $rawScore = $baseScore * ($percentage / 100.0);
        return $this->applyCap($rawScore, $type);
    }

    // KRA II, Criterion A: (Translated Outputs)
    public function calculateTranslatedOutputLeadScore(array $data, string $type): array
    {
        $dateCompleted = $data['date_completed'] ?? null;
        $dateUtilized = $data['date_utilized'] ?? null;
        $rawScore = (!empty($dateCompleted) || !empty($dateUtilized)) ? 35.0 : 0.0;
        return $this->applyCap($rawScore, $type);
    }

    public function calculateTranslatedOutputContributorScore(array $data, string $type): array
    {
        $dateCompleted = $data['date_completed'] ?? null;
        $dateUtilized = $data['date_utilized'] ?? null;
        $percentage = (float)($data['contribution_percentage'] ?? 0);
        if ((empty($dateCompleted) && empty($dateUtilized)) || $percentage <= 0) return ['raw' => 0.0, 'score' => 0.0];

        $rawScore = 35.0 * ($percentage / 100.0); // Base score * percentage
        return $this->applyCap($rawScore, $type);
    }


    // KRA II, Criterion A: (Citations)
    public function calculateResearchCitationLocalScore(array $data, string $type): array
    {
        $citationCount = (int)($data['citation_count'] ?? 0);
        if ($citationCount <= 0) return ['raw' => 0.0, 'score' => 0.0];

        $rawScore = (float)$citationCount * 5.0;
        return $this->applyCap($rawScore, $type);
    }

    public function calculateResearchCitationInternationalScore(array $data, string $type): array
    {
        $citationCount = (int)($data['citation_count'] ?? 0);
        if ($citationCount <= 0) return ['raw' => 0.0, 'score' => 0.0];

        $rawScore = (float)$citationCount * 10.0;
        return $this->applyCap($rawScore, $type);
    }

    // KRA II, Criterion B (Patented Inventions)
    public function calculateInventionPatentSoleScore(array $data, string $type): array
    {
        $stage = $data['patent_stage'] ?? null;
        if (!$stage) return ['raw' => 0.0, 'score' => 0.0];

        $rawScore = $this->getInventionPatentBaseScore($stage);
        return $this->applyCap($rawScore, $type);
    }

    public function calculateInventionPatentCoInventorScore(array $data, string $type): array
    {
        $stage = $data['patent_stage'] ?? null;
        $percentage = (float)($data['contribution_percentage'] ?? 0);
        if (!$stage || $percentage <= 0) return ['raw' => 0.0, 'score' => 0.0];

        $baseScore = $this->getInventionPatentBaseScore($stage);
        $rawScore = $baseScore * ($percentage / 100.0);
        return $this->applyCap($rawScore, $type);
    }

    // KRA II, Criterion B (Utility/Design)
    public function calculateUtilityDesignSoleScore(array $data, string $type): array
    {
        $pType = $data['patent_type'] ?? null;
        $dateGranted = $data['date_granted'] ?? null;
        if (!$pType || empty($dateGranted)) return ['raw' => 0.0, 'score' => 0.0];

        $rawScore = match ($pType) {
            'utility_model' => 10.0,
            'industrial_design' => 5.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    public function calculateUtilityDesignCoInventorScore(array $data, string $type): array
    {
        $pType = $data['patent_type'] ?? null;
        $dateGranted = $data['date_granted'] ?? null;
        $percentage = (float)($data['contribution_percentage'] ?? 0);
        if (!$pType || empty($dateGranted) || $percentage <= 0) return ['raw' => 0.0, 'score' => 0.0];

        $baseScore = match ($pType) {
            'utility_model' => 10.0,
            'industrial_design' => 5.0,
            default => 0.0,
        };
        $rawScore = $baseScore * ($percentage / 100.0);
        return $this->applyCap($rawScore, $type);
    }

    // KRA II, Criterion B (Commercialized)
    public function calculateCommercializedLocalScore(array $data, string $type): array
    {
        $datePatented = $data['date_patented'] ?? null;
        $dateCommercialized = $data['date_commercialized'] ?? null;
        if (empty($datePatented) || empty($dateCommercialized)) return ['raw' => 0.0, 'score' => 0.0];

        $rawScore = 5.0;
        return $this->applyCap($rawScore, $type);
    }

    public function calculateCommercializedInternationalScore(array $data, string $type): array
    {
        $datePatented = $data['date_patented'] ?? null;
        $dateCommercialized = $data['date_commercialized'] ?? null;
        if (empty($datePatented) || empty($dateCommercialized)) return ['raw' => 0.0, 'score' => 0.0];

        $rawScore = 10.0;
        return $this->applyCap($rawScore, $type);
    }

    // KRA II, Criterion B (Non-Patentable: New Software)
    public function calculateSoftwareNewSoleScore(array $data, string $type): array
    {
        $copyrightNo = $data['copyright_no'] ?? null;
        $dateCopyrighted = $data['date_copyrighted'] ?? null;
        $dateUtilized = $data['date_utilized'] ?? null;
        $rawScore = (!empty($copyrightNo) && (!empty($dateCopyrighted) || !empty($dateUtilized))) ? 10.0 : 0.0;
        return $this->applyCap($rawScore, $type);
    }

    public function calculateSoftwareNewCoDeveloperScore(array $data, string $type): array
    {
        $copyrightNo = $data['copyright_no'] ?? null;
        $dateCopyrighted = $data['date_copyrighted'] ?? null;
        $dateUtilized = $data['date_utilized'] ?? null;
        $percentage = (float)($data['contribution_percentage'] ?? 0);
        if (empty($copyrightNo) || (empty($dateCopyrighted) && empty($dateUtilized)) || $percentage <= 0) return ['raw' => 0.0, 'score' => 0.0];

        $rawScore = 10.0 * ($percentage / 100.0); // Base score * percentage
        return $this->applyCap($rawScore, $type);
    }

    // KRA II, Criterion B (Non-Patentable: Updated Software)
    public function calculateSoftwareUpdatedScore(array $data, string $type): array
    {
        $copyrightNo = $data['copyright_no'] ?? null;
        $dateCopyrighted = $data['date_copyrighted'] ?? null;
        $dateUtilized = $data['date_utilized'] ?? null;
        $role = $data['developer_role'] ?? null;

        if ((empty($dateCopyrighted) && empty($dateUtilized)) || empty($role)) return ['raw' => 0.0, 'score' => 0.0];

        $rawScore = match (strtolower($role)) {
            'sole developer' => 4.0,
            'co-developer' => 2.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }


    // KRA II, Criterion B (Non-Patentable: Plant/Animal/Microbe)
    public function calculatePlantAnimalSoleScore(array $data, string $type): array
    {
        $dateRegistered = $data['date_registered'] ?? null;
        $datePropagation = $data['date_propagation'] ?? null;
        $rawScore = (!empty($dateRegistered) && !empty($datePropagation)) ? 10.0 : 0.0;
        return $this->applyCap($rawScore, $type);
    }

    public function calculatePlantAnimalCoDeveloperScore(array $data, string $type): array
    {
        $dateRegistered = $data['date_registered'] ?? null;
        $datePropagation = $data['date_propagation'] ?? null;
        $percentage = (float)($data['contribution_percentage'] ?? 0);
        if (empty($dateRegistered) || empty($datePropagation) || $percentage <= 0) return ['raw' => 0.0, 'score' => 0.0];

        $rawScore = 10.0 * ($percentage / 100.0); // Base score * percentage
        return $this->applyCap($rawScore, $type);
    }

    // KRA II, Criterion C
    public function calculateCreativePerformingArtScore(array $data, string $type): array
    {
        $title = $data['title'] ?? null;
        $artType = $data['art_type'] ?? null;
        $datePerformed = $data['date_performed'] ?? null;
        $classification = $data['classification'] ?? null;

        if (empty($title) || empty($datePerformed) || empty($artType) || $artType === 'select_option' || empty($classification) || $classification === 'select_option') {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = match ($classification) {
            'new_creation' => 20.0,
            'own_work' => 10.0,
            'work_of_others' => 10.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    public function calculateCreativeExhibitionScore(array $data, string $type): array
    {
        $title = $data['title'] ?? null;
        $classification = $data['classification'] ?? null;
        $creativeType = $data['creative_type'] ?? null;
        $dateExhibited = $data['date_exhibited'] ?? null;
        $organizer = $data['organizer'] ?? null;
        $venue = $data['venue'] ?? null;

        if (
            empty($title) ||
            empty($classification) || $classification === 'select_option' ||
            empty($creativeType) || $creativeType === 'select_option' ||
            empty($dateExhibited) ||
            empty($organizer) ||
            empty($venue)
        ) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = 20.0;
        return $this->applyCap($rawScore, $type);
    }

    public function calculateCreativeJuriedDesignScore(array $data, string $type): array
    {
        $title = $data['title'] ?? null;
        $classification = $data['classification'] ?? null;
        $reviewer = $data['reviewer'] ?? null;
        $dateActivity = $data['date_activity'] ?? null;
        $venue = $data['venue'] ?? null;
        $organizer = $data['organizer'] ?? null;

        if (
            empty($title) ||
            empty($classification) || $classification === 'select_option' ||
            empty($reviewer) ||
            empty($dateActivity) ||
            empty($venue) ||
            empty($organizer)
        ) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = 20.0;
        return $this->applyCap($rawScore, $type);
    }

    public function calculateCreativeLiteraryPublicationScore(array $data, string $type): array
    {
        $publisher = $data['publisher'] ?? null;
        $datePublished = $data['date_published'] ?? null;
        $literaryType = $data['literary_type'] ?? null;

        if (empty($publisher) || empty($datePublished) || empty($literaryType) || $literaryType === 'select_option') {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = match ($literaryType) {
            'novel' => 20.0,
            'short_story' => 10.0,
            'essay' => 10.0,
            'poetry' => 10.0,
            'others' => 10.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    // KRA III, Criterion A
    public function calculateExtensionLinkageScore(array $data, string $type): array
    {
        $partnerName = $data['partner_name'] ?? null;
        $moaStart = $data['moa_start'] ?? null;
        $facultyRole = $data['faculty_role'] ?? null;
        $moaExpiration = $data['moa_expiration'] ?? null;
        $activities = $data['activities'] ?? null;
        $activityDate = $data['activity_date'] ?? null;
        $nature = $data['nature'] ?? null;

        if (
            empty($partnerName) || empty($moaStart) ||
            empty($facultyRole) || $facultyRole === 'select_option' ||
            empty($moaExpiration) || empty($activities) ||
            empty($activityDate) || empty($nature)
        ) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = match ($facultyRole) {
            'lead_coordinator' => 5.0,
            'assistant_coordinator' => 3.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    public function calculateExtensionIncomeGenerationScore(array $data, string $type): array
    {
        $name = $data['name'] ?? null;
        $coverageStart = $data['coverage_start'] ?? null;
        $role = $data['role'] ?? null;
        $amount = isset($data['amount']) && is_numeric($data['amount']) ? (float)$data['amount'] : null;

        if (empty($name) || empty($coverageStart) || $amount === null || empty($role) || $role === 'select_option') {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        if ($amount <= 0) return ['raw' => 0.0, 'score' => 0.0];

        $rawScore = 0.0;
        if ($role === 'lead_contributor') {
            if ($amount <= 6000000) $rawScore = 6.0;
            elseif ($amount <= 12000000) $rawScore = 12.0;
            else $rawScore = 18.0;
        } elseif ($role === 'co_contributor') {
            if ($amount <= 6000000) $rawScore = 3.0;
            elseif ($amount <= 12000000) $rawScore = 6.0;
            else $rawScore = 9.0;
        }

        return $this->applyCap($rawScore, $type);
    }

    // KRA III, Criterion B
    public function calculateAccreditationServiceScore(array $data, string $type): array
    {
        $agencyName = $data['agency_name'] ?? null;
        $servicesProvided = $data['services_provided'] ?? null;
        $scope = $data['scope'] ?? null;
        $periodStart = $data['period_start'] ?? null;
        $deploymentCount = isset($data['deployment_count']) && is_numeric($data['deployment_count']) ? (int)$data['deployment_count'] : null;

        if (
            empty($agencyName) || empty($servicesProvided) || empty($scope) || $scope === 'select_option' ||
            empty($periodStart) ||
            $deploymentCount === null || $deploymentCount <= 0
        ) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = match ($scope) {
            'local' => 8.0,
            'international' => 10.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    public function calculateJudgeExaminerScore(array $data, string $type): array
    {
        $eventTitle = $data['event_title'] ?? null;
        $organizer = $data['organizer'] ?? null;
        $eventDate = $data['event_date'] ?? null;
        $awardNature = $data['award_nature'] ?? null;
        $role = $data['role'] ?? null;

        if (
            empty($eventTitle) || empty($organizer) || empty($eventDate) ||
            empty($awardNature) || $awardNature === 'select_option' || empty($role)
        ) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = match ($awardNature) {
            'research_award' => 2.0,
            'academic_competition' => 1.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    public function calculateConsultantScore(array $data, string $type): array
    {
        $projectTitle = $data['project_title'] ?? null;
        $organizationName = $data['organization_name'] ?? null;
        $periodStart = $data['period_start'] ?? null;
        $periodEnd = $data['period_end'] ?? null;
        $scope = $data['scope'] ?? null;
        $role = $data['role'] ?? null;

        if (
            empty($projectTitle) || empty($organizationName) || empty($periodStart) ||
            empty($periodEnd) || empty($scope) || $scope === 'select_option' || empty($role)
        ) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = match ($scope) {
            'local' => 8.0,
            'international' => 10.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    public function calculateMediaServiceScore(array $data, string $type): array
    {
        $service = $data['service'] ?? null;
        $mediaName = $data['media_name'] ?? null;
        $periodStart = $data['period_start'] ?? null;
        $engagementCount = isset($data['engagement_count']) && is_numeric($data['engagement_count']) ? (int)$data['engagement_count'] : null;

        if (empty($service) || $service === 'select_option' || empty($periodStart)) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = match ($service) {
            'writer_occasional_newspaper' => (!empty($engagementCount) && $engagementCount > 0) ? ((float)$engagementCount * 2.0) : 0.0,
            'writer_regular_newspaper'    => (!empty($mediaName)) ? 10.0 : 0.0,
            'host_tv_radio_program'     => (!empty($mediaName)) ? 10.0 : 0.0,
            'guest_technical_expert'      => (!empty($engagementCount) && $engagementCount > 0) ? ((float)$engagementCount * 1.0) : 0.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    public function calculateTrainingResourcePersonScore(array $data, string $type): array
    {
        $trainingTitle = $data['training_title'] ?? null;
        $participationType = $data['participation_type'] ?? null;
        $organizer = $data['organizer'] ?? null;
        $periodStart = $data['period_start'] ?? null;
        $periodEnd = $data['period_end'] ?? null;
        $scope = $data['scope'] ?? null;
        $totalHours = isset($data['total_hours']) && is_numeric($data['total_hours']) ? (int)$data['total_hours'] : null;

        if (
            empty($trainingTitle) || empty($participationType) || empty($organizer) ||
            empty($periodStart) || empty($periodEnd) ||
            empty($scope) || $scope === 'select_option' ||
            $totalHours === null || $totalHours <= 0
        ) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = match ($scope) {
            'local' => 2.0 * (float)$totalHours,
            'international' => 3.0 * (float)$totalHours,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    public function calculateSocialResponsibilityScore(array $data, string $type): array
    {
        $activityTitle = $data['activity_title'] ?? null;
        $communityName = $data['community_name'] ?? null;
        $beneficiaryCount = isset($data['beneficiary_count']) && is_numeric($data['beneficiary_count']) ? (int)$data['beneficiary_count'] : null;
        $role = $data['role'] ?? null;
        $activityDate = $data['activity_date'] ?? null;

        if (
            empty($activityTitle) || empty($communityName) ||
            $beneficiaryCount === null || $beneficiaryCount <= 0 ||
            empty($role) || $role === 'select_option' ||
            empty($activityDate)
        ) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = match ($role) {
            'head' => 5.0,
            'participant' => 2.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }


    // KRA III, Criterion C
    public function calculateQualityOfExtensionScore(array $data, string $type): array
    {
        $average = $this->calculateSpecialAverage(
            $this->getExtensionRatingFieldKeys(),
            $data,
            (int)($data['client_deducted_semesters'] ?? 0),
            $data['client_deduction_reason'] ?? 'NOT APPLICABLE'
        );

        $cappedAverage = min($average, 100.0);
        $rawScore = $average;
        $score = $cappedAverage * $this->getCap('kra_3_c_cap') / 100.0;

        return ['raw' => $rawScore, 'score' => $score];
    }

    // KRA III, Criterion D (Bonus)
    public function calculateBonusDesignationScore(array $data, string $type): array
    {
        $designation = $data['designation'] ?? null;
        $periodStart = $data['period_start'] ?? null;
        $periodEnd = $data['period_end'] ?? null;

        if (empty($designation) || $designation === 'select_option' || empty($periodStart) || empty($periodEnd)) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = match ($designation) {
            'president_oic' => 20.0,
            'vice_president' => 15.0,
            'chancellor' => 10.0,
            'vice_chancellor' => 8.0,
            'campus_director' => 8.0,
            'faculty_regent' => 8.0,
            'office_director' => 6.0,
            'university_college_secretary' => 6.0,
            'dean' => 6.0,
            'associate_dean' => 5.0,
            'project_head_kra3d' => 4.0,
            'department_head' => 4.0,
            'institution_committee_chair' => 3.0,
            'college_secretary' => 3.0,
            'program_chair' => 3.0,
            'institution_committee_member' => 2.0,
            'department_committee_chair' => 2.0,
            'department_committee_member' => 1.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    // KRA IV, Criterion A
    public function calculateProfOrgScore(array $data, string $type): array
    {
        $orgName = $data['name'] ?? null;
        $orgType = $data['type'] ?? null;
        $activity = $data['activity'] ?? null;
        $dateActivity = $data['date_activity'] ?? null;
        $role = $data['role'] ?? null;

        if (
            empty($orgName) || empty($orgType) || empty($activity) ||
            empty($dateActivity) || empty($role) || $role === 'select_option'
        ) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = 5.0;
        return $this->applyCap($rawScore, $type);
    }

    // KRA IV, Criterion B (Educational Qualifications)
    public function calculateDoctorateDegreeScore(array $data, string $type): array
    {
        $degreeName = $data['name'] ?? null;
        $institution = $data['institution'] ?? null;
        $dateCompleted = $data['date_completed'] ?? null;
        $isQualified = isset($data['is_qualified']) ? (bool)$data['is_qualified'] : null;

        if (empty($degreeName) || empty($institution) || empty($dateCompleted) || $isQualified === null) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = !$isQualified ? 40.0 : 0.0;
        return $this->applyCap($rawScore, $type);
    }

    public function calculateAdditionalDegreeScore(array $data, string $type): array
    {
        $degreeType = $data['degree_type'] ?? null;
        $degreeName = $data['name'] ?? null;
        $institution = $data['institution'] ?? null;
        $dateCompleted = $data['date_completed'] ?? null;

        if (empty($degreeName) || empty($institution) || empty($dateCompleted) || empty($degreeType) || $degreeType === 'select_option') {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = match ($degreeType) {
            'additional_doctorate' => 40.0,
            'additional_masters' => 20.0,
            'post_doctorate_diploma' => 10.0,
            'post_masters_diploma' => 10.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    // KRA IV, Criterion B (Conferences/Training)
    public function calculateConferenceTrainingScore(array $data, string $type): array
    {
        $confName = $data['name'] ?? null;
        $scope = $data['scope'] ?? null;

        if (empty($confName) || empty($scope) || $scope === 'select_option') {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = match ($scope) {
            'local' => 1.0,
            'international' => 2.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    // KRA IV, Criterion B (Paper Presentations)
    public function calculatePaperPresentationScore(array $data, string $type): array
    {
        $paperTitle = $data['title'] ?? null;
        $scope = $data['scope'] ?? null;

        if (empty($paperTitle) || empty($scope) || $scope === 'select_option') {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = match ($scope) {
            'local' => 3.0,
            'international' => 5.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    // KRA IV, Criterion C (Awards & Recognition)
    public function calculateAwardRecognitionScore(array $data, string $type): array
    {
        $awardName = $data['name'] ?? null;
        $awardingBody = $data['awarding_body'] ?? null;
        $dateGiven = $data['date_given'] ?? null;
        $venue = $data['venue'] ?? null;
        $scope = $data['scope'] ?? null;

        if (
            empty($awardName) || empty($awardingBody) || empty($dateGiven) ||
            empty($venue) || empty($scope) || $scope === 'select_option'
        ) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $rawScore = match ($scope) {
            'institutional' => 2.0,
            'local' => 3.0,
            'regional' => 4.0,
            default => 0.0,
        };
        return $this->applyCap($rawScore, $type);
    }

    // KRA IV, Criterion D (Bonus - Academic Service)
    public function calculateAcademicServiceScore(array $data, string $type): array
    {
        $designation = $data['designation'] ?? null;
        $heiName = $data['hei_name'] ?? null;
        $periodStart = $data['period_start'] ?? null;
        $periodEnd = $data['period_end'] ?? null;
        $numYears = isset($data['no_of_years']) && is_numeric($data['no_of_years']) ? (float)$data['no_of_years'] : null;
        if (
            empty($designation) || $designation === 'select_option' || empty($heiName) ||
            empty($periodStart) || empty($periodEnd) || $numYears === null || $numYears <= 0
        ) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $multiplier = match ($designation) {
            'president' => 5.0,
            'vp_dean_director' => 4.0,
            'dept_program_head' => 3.0,
            'faculty_member' => 2.0,
            default => 0.0,
        };

        $rawScore = $multiplier * $numYears;
        return $this->applyCap($rawScore, $type);
    }

    // KRA IV, Criterion D (Bonus - Industry Experience)
    public function calculateIndustryExperienceScore(array $data, string $type): array
    {
        $orgName = $data['org_name'] ?? null;
        $designation = $data['designation'] ?? null;
        $periodStart = $data['period_start'] ?? null;
        $periodEnd = $data['period_end'] ?? null;
        $numYears = isset($data['no_of_years']) && is_numeric($data['no_of_years']) ? (float)$data['no_of_years'] : null;

        if (
            empty($orgName) || empty($designation) || $designation === 'select_option' ||
            empty($periodStart) || empty($periodEnd) || $numYears === null || $numYears <= 0
        ) {
            return ['raw' => 0.0, 'score' => 0.0];
        }

        $multiplier = match ($designation) {
            'managerial_supervisory' => 4.0,
            'technical_skilled' => 3.0,
            'support_administrative' => 2.0,
            default => 0.0,
        };

        $rawScore = $multiplier * $numYears;
        return $this->applyCap($rawScore, $type);
    }

    // Private Helpers
    private function calculateSpecialAverage(array $ratingKeys, array $data, int $deductedSemesters, string $reasonForDeducting = 'NOT APPLICABLE'): float
    {
        $ratings = [];
        $providedRatingsCount = 0;

        foreach ($ratingKeys as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                $maxValue = 100.0;
                $ratings[] = min((float)$data[$key], $maxValue);
                $providedRatingsCount++;
            } elseif (isset($data[$key])) {
                $providedRatingsCount++;
            }
        }

        if ($providedRatingsCount === 0) {
            return 0.0;
        }

        $totalSemesters = count($ratingKeys);

        $isValidDeduction = $reasonForDeducting !== 'NOT APPLICABLE' && $reasonForDeducting !== 'SELECT OPTION';

        $divisor = $totalSemesters;
        if ($isValidDeduction && $deductedSemesters > 0 && $deductedSemesters < $totalSemesters) {
            $divisor = $totalSemesters - $deductedSemesters;
        } elseif ($providedRatingsCount < $totalSemesters && !$isValidDeduction) {
            $divisor = $providedRatingsCount;
        }
        $divisor = max(1, $divisor);

        $sum = empty($ratings) ? 0.0 : array_sum($ratings);

        return $sum / $divisor;
    }

    private function getRatingFieldKeys(string $prefix): array
    {
        $keys = [];
        for ($year = 1; $year <= 4; $year++) {
            for ($sem = 1; $sem <= 2; $sem++) {
                $keys[] = "{$prefix}_ay{$year}_sem{$sem}";
            }
        }
        return $keys;
    }

    // Helper for KRA I C (Mentorship)
    private function getMentorshipAcademicYearKeys(): array
    {
        return ['ay_1_count', 'ay_2_count', 'ay_3_count', 'ay_4_count'];
    }

    private function getMentorshipAcademicYearTotal(array $data): int
    {
        $total = 0;
        foreach ($this->getMentorshipAcademicYearKeys() as $key) {
            $total += (int)($data[$key] ?? 0);
        }
        return $total;
    }

    // Helper for KRA I B (Instructional Materials)
    private function getInstructionalMaterialBaseScore(?string $materialType): float
    {
        if (!$materialType) return 0.0;
        return match ($materialType) {
            'textbook' => 30.0,
            'textbook_chapter' => 10.0,
            'manual_module' => 16.0,
            'multimedia_material' => 16.0,
            'testing_material' => 10.0,
            default => 0.0,
        };
    }

    // Helper for KRA II A (Published Papers)
    private function getResearchBaseScore(?string $outputType): float
    {
        if (!$outputType) return 0.0;
        return match ($outputType) {
            'book' => 100.0,
            'journal_article' => 50.0,
            'book_chapter' => 35.0,
            'monograph' => 100.0,
            'other_peer_reviewed_output' => 10.0,
            default => 0.0,
        };
    }

    // Helper for KRA II B (Patented Inventions)
    private function getInventionPatentBaseScore(?string $stage): float
    {
        if (!$stage) return 0.0;
        return match (strtolower($stage)) {
            'accepted' => 10.0,
            'published' => 20.0,
            'granted' => 80.0,
            default => 0.0,
        };
    }

    // Helper for KRA III C field keys
    private function getExtensionRatingFieldKeys(): array
    {
        $prefix = 'client';
        $keys = [];
        for ($year = 1; $year <= 4; $year++) {
            for ($sem = 1; $sem <= 2; $sem++) {
                $keys[] = "{$prefix}_ay{$year}_sem{$sem}";
            }
        }
        return $keys;
    }
}
