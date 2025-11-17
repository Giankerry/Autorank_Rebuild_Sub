<?php

namespace App\Services;

use App\Models\Application;
use App\Models\FacultyRank;
use App\Models\KraWeight;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class ApplicationScoringService
{
    private ScoringService $submissionService;
    private array $caps = [];

    public function __construct(ScoringService $submissionService)
    {
        $this->submissionService = $submissionService;
        $this->caps = Setting::all()->pluck('value', 'key')->all();
    }

    private function getCap(string $key): float
    {
        return (float)($this->caps[$key] ?? 0);
    }

    private const KRA1_A_TYPES = ['te-student-evaluation', 'te-supervisor-evaluation'];
    private const KRA1_B_TYPES = ['im-sole-authorship', 'im-co-authorship', 'im-academic-program'];
    private const KRA1_C_TYPES = ['mentorship-adviser', 'mentorship-panel', 'mentorship-mentor'];

    private const KRA2_A_TYPES = ['research-sole-authorship', 'research-co-authorship', 'research-translated-lead', 'research-translated-contributor'];
    private const KRA2_A31_TYPES = ['research-citation-local'];
    private const KRA2_A32_TYPES = ['research-citation-international'];

    private const KRA2_B_TYPES = ['invention-patent-sole', 'invention-patent-co-inventor', 'invention-utility-design-sole', 'invention-utility-design-co-inventor', 'invention-commercialized-local', 'invention-commercialized-international', 'invention-software-new-sole', 'invention-software-new-co', 'invention-software-updated', 'invention-plant-animal-sole', 'invention-plant-animal-co'];
    private const KRA2_B121_TYPES = ['r-and-d-patented-local'];
    private const KRA2_B122_TYPES = ['r-and-d-patented-international'];
    private const KRA2_C_TYPES = ['creative-performing-art', 'creative-exhibition', 'creative-juried-design', 'creative-literary-publication'];

    private const KRA3_A_TYPES = ['extension-linkage', 'extension-income-generation'];
    private const KRA3_B_TYPES = ['accreditation_services', 'judge_examiner', 'consultant', 'media_service', 'training_resource_person', 'social_responsibility'];
    private const KRA3_C_TYPES = ['extension-quality-rating'];
    private const KRA3_D_TYPES = ['extension-bonus-designation'];

    private const KRA4_A_TYPES = ['profdev-organization'];
    private const KRA4_B_TYPES = ['profdev-doctorate', 'profdev-additional-degree', 'profdev-conference-training', 'profdev-paper-presentation'];
    private const KRA4_B2_TYPES = ['training-participation'];
    private const KRA4_B3_TYPES = ['training-paper-presentation'];
    private const KRA4_C_TYPES = ['profdev-award-recognition'];
    private const KRA4_D_TYPES = ['profdev-academic-service', 'profdev-industry-experience'];

    public function calculateScore(Application $application): void
    {
        foreach ($application->submissions as $submission) {
            $this->submissionService->calculateScore($submission);
            $submission->saveQuietly();
        }

        // KRA 1
        $kra1a_score = $application->submissions->whereIn('type', self::KRA1_A_TYPES)->sum('score');
        $kra1b_raw   = $application->submissions->whereIn('type', self::KRA1_B_TYPES)->sum('raw_score');
        $kra1b_score = min($kra1b_raw, $this->getCap('kra_1_b_cap'));
        $kra1c_raw   = $application->submissions->whereIn('type', self::KRA1_C_TYPES)->sum('raw_score');
        $kra1c_score = min($kra1c_raw, $this->getCap('kra_1_c_cap'));

        $kra1_sum = $kra1a_score + $kra1b_score + $kra1c_score;
        $application->kra1_score = min($kra1_sum, 100.0);

        // KRA 2
        $kra2a_raw     = $application->submissions->whereIn('type', self::KRA2_A_TYPES)->sum('raw_score');
        $kra2a_score   = min($kra2a_raw, $this->getCap('kra_2_a_cap'));
        $kra2a31_raw   = $application->submissions->whereIn('type', self::KRA2_A31_TYPES)->sum('raw_score');
        $kra2a31_score = min($kra2a31_raw, $this->getCap('kra_2_a_3_1_cap'));
        $kra2a32_raw   = $application->submissions->whereIn('type', self::KRA2_A32_TYPES)->sum('raw_score');
        $kra2a32_score = min($kra2a32_raw, $this->getCap('kra_2_a_3_2_cap'));

        $kra2b_raw     = $application->submissions->whereIn('type', self::KRA2_B_TYPES)->sum('raw_score');
        $kra2b_score   = min($kra2b_raw, $this->getCap('kra_2_b_cap'));
        $kra2b121_raw  = $application->submissions->whereIn('type', self::KRA2_B121_TYPES)->sum('raw_score');
        $kra2b121_score = min($kra2b121_raw, $this->getCap('kra_2_b_1_2_1_cap'));
        $kra2b122_raw  = $application->submissions->whereIn('type', self::KRA2_B122_TYPES)->sum('raw_score');
        $kra2b122_score = min($kra2b122_raw, $this->getCap('kra_2_b_1_2_2_cap'));

        $kra2c_raw     = $application->submissions->whereIn('type', self::KRA2_C_TYPES)->sum('raw_score');
        $kra2c_score   = min($kra2c_raw, $this->getCap('kra_2_c_cap'));

        $kra2_sum = $kra2a_score + $kra2a31_score + $kra2a32_score + $kra2b_score + $kra2b121_score + $kra2b122_score + $kra2c_score;
        $application->kra2_score = min($kra2_sum, 100.0);

        // KRA 3
        $kra3a_raw   = $application->submissions->whereIn('type', self::KRA3_A_TYPES)->sum('raw_score');
        $kra3a_score = min($kra3a_raw, $this->getCap('kra_3_a_cap'));
        $kra3b_raw   = $application->submissions->whereIn('type', self::KRA3_B_TYPES)->sum('raw_score');
        $kra3b_score = min($kra3b_raw, $this->getCap('kra_3_b_cap'));
        $kra3c_score = $application->submissions->whereIn('type', self::KRA3_C_TYPES)->sum('score');
        $kra3d_raw   = $application->submissions->whereIn('type', self::KRA3_D_TYPES)->sum('raw_score');
        $kra3d_score = min($kra3d_raw, $this->getCap('kra_3_d_cap'));

        $kra3_sum = $kra3a_score + $kra3b_score + $kra3c_score + $kra3d_score;
        $application->kra3_score = min($kra3_sum, 100.0);

        // KRA 4
        $kra4a_raw    = $application->submissions->whereIn('type', self::KRA4_A_TYPES)->sum('raw_score');
        $kra4a_score  = min($kra4a_raw, $this->getCap('kra_4_a_cap'));
        $kra4b_raw    = $application->submissions->whereIn('type', self::KRA4_B_TYPES)->sum('raw_score');
        $kra4b_score  = min($kra4b_raw, $this->getCap('kra_4_b_cap'));
        $kra4b2_raw   = $application->submissions->whereIn('type', self::KRA4_B2_TYPES)->sum('raw_score');
        $kra4b2_score = min($kra4b2_raw, $this->getCap('kra_4_b_2_cap'));
        $kra4b3_raw   = $application->submissions->whereIn('type', self::KRA4_B3_TYPES)->sum('raw_score');
        $kra4b3_score = min($kra4b3_raw, $this->getCap('kra_4_b_3_cap'));
        $kra4c_raw    = $application->submissions->whereIn('type', self::KRA4_C_TYPES)->sum('raw_score');
        $kra4c_score  = min($kra4c_raw, $this->getCap('kra_4_c_cap'));
        $kra4d_raw    = $application->submissions->whereIn('type', self::KRA4_D_TYPES)->sum('raw_score');
        $kra4d_score  = min($kra4d_raw, $this->getCap('kra_4_d_cap'));

        $kra4_sum = $kra4a_score + $kra4b_score + $kra4b2_score + $kra4b3_score + $kra4c_score + $kra4d_score;
        $application->kra4_score = min($kra4_sum, 100.0);

        $currentRankName = $application->applicant_current_rank ?? 'N/A';

        $currentRank = FacultyRank::where('name', $currentRankName)->first();
        if (!$currentRank) {
            Log::error("AppScoringService: Could not find rank '{$currentRankName}' in faculty_ranks.");
            $application->final_score = 0;
            $application->highest_attainable_rank = 'Current Rank Not Found';
            return;
        }

        $rankCategory = $this->getRankCategoryFromLevel($currentRank->level);
        $weights = KraWeight::where('rank_category', $rankCategory)->first();

        if (!$weights) {
            Log::error("AppScoringService: No weights found for category '{$rankCategory}' (App ID: {$application->id})");
            $application->final_score = 0;
            $application->highest_attainable_rank = 'Weights Not Found';
            return;
        }

        $finalScore =
            ($application->kra1_score * ($weights->kra1_weight / 100.0)) +
            ($application->kra2_score * ($weights->kra2_weight / 100.0)) +
            ($application->kra3_score * ($weights->kra3_weight / 100.0)) +
            ($application->kra4_score * ($weights->kra4_weight / 100.0));

        $application->final_score = $finalScore;
        $application->highest_attainable_rank = $this->determineNewRank($application, $finalScore, $currentRank);
    }

    private function determineNewRank(Application $application, float $finalScore, FacultyRank $currentRank): string
    {
        $currentLevel = $currentRank->level;

        $autoSubRankIncrease = 0;
        $doctorateSubmission = $application->submissions
            ->where('type', 'profdev-doctorate')
            ->first();

        if ($doctorateSubmission && ($doctorateSubmission->data['is_qualified'] ?? false) === true) {
            $autoSubRankIncrease = 1;
        }

        $scoreSubRankIncrease = 0;
        if ($finalScore >= 91) {
            $scoreSubRankIncrease = 6;
        } elseif ($finalScore >= 81) {
            $scoreSubRankIncrease = 5;
        } elseif ($finalScore >= 71) {
            $scoreSubRankIncrease = 4;
        } elseif ($finalScore >= 61) {
            $scoreSubRankIncrease = 3;
        } elseif ($finalScore >= 51) {
            $scoreSubRankIncrease = 2;
        } elseif ($finalScore >= 41) {
            $scoreSubRankIncrease = 1;
        }

        $newLevel = $currentLevel + $autoSubRankIncrease + $scoreSubRankIncrease;

        $newRank = FacultyRank::where('level', $newLevel)->first();

        if (!$newRank) {
            $maxRank = FacultyRank::orderBy('level', 'desc')->first();
            if ($newLevel > $currentLevel) {
                return $maxRank ? $maxRank->name : 'Max Rank Reached';
            }
            return $currentRank->name;
        }

        return $newRank->name;
    }

    private function getRankCategoryFromLevel(int $level): string
    {
        if ($level >= 1 && $level <= 3) {
            return 'Category 1';
        }
        if ($level >= 4 && $level <= 7) {
            return 'Category 2';
        }
        if ($level >= 8 && $level <= 12) {
            return 'Category 3';
        }
        if ($level >= 13) {
            return 'Category 4';
        }

        return 'Category 1';
    }
}
