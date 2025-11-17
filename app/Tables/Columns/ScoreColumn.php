<?php

namespace App\Tables\Columns;

use App\Models\Submission;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class ScoreColumn extends TextColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Score')
            ->badge()
            ->numeric(2)
            ->description(function (Model $record) {

                if (! ($record instanceof Submission)) {
                    return null;
                }

                $raw = (float) $record->raw_score;
                $capped = (float) $record->score;
                $type = $record->type;

                if (in_array($type, ['te-student-evaluation', 'te-supervisor-evaluation', 'extension-quality-rating'])) {

                    $average = $raw;
                    $totalCap = match ($type) {
                        'te-student-evaluation' => $this->getCap('kra_1_a_cap') * 0.60,
                        'te-supervisor-evaluation' => $this->getCap('kra_1_a_cap') * 0.40,
                        'extension-quality-rating' => $this->getCap('kra_3_c_cap'),
                        default => 0,
                    };

                    $rawPoints = $average * $totalCap / 100.0;

                    $lost = round($rawPoints - $capped, 2);

                    if ($lost <= 0.01) {
                        return null;
                    }

                    return "Capped at " . number_format($capped, 2) . " (Lost " . number_format($lost, 2) . " pts)";
                } else {
                    $lost = round($raw - $capped, 2);

                    if ($lost > 0.01) {
                        return "Capped at " . number_format($capped, 2) . " (Lost " . number_format($lost, 2) . " pts)";
                    }
                }

                return null;
            });
    }

    private function getCap(string $key): float
    {
        $caps = \App\Models\Setting::all()->pluck('value', 'key')->all();
        return (float)($caps[$key] ?? 0);
    }
}
