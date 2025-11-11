<?php

namespace App\Tables\Actions;

use App\Models\Submission;
use Filament\Tables\Actions\Action;
use App\Filament\Instructor\Widgets\BaseKRAWidget;

/**
 * @method BaseKRAWidget getLivewire()
 */
class ViewSubmissionFilesAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('View Files')
            ->icon('heroicon-c-eye')
            ->modalHeading(function (Submission $record) {
                $title = $record->data['title'] ?? $record->data['program_name'] ?? 'Submission Files';
                return 'Viewing: ' . $title;
            })
            ->modalWidth('6xl')
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->visible(fn(Submission $record): bool => !empty($record->google_drive_file_id))
            ->modalContent(function (Submission $record) {

                $fileViewRoutes = [];
                $fileIds = $record->google_drive_file_id ?? [];

                foreach ($fileIds as $key => $fileId) {
                    $fileViewRoutes['File ' . ($key + 1)] = route('submission.file.view', [
                        'submission' => $record->id,
                        'fileKey' => $key
                    ]);
                }

                $formattingMap = $this->getLivewire()->getDisplayFormattingMap();

                return view('filament.modals.view-submission-files', [
                    'submissionData' => $record->data,
                    'fileViewRoutes' => $fileViewRoutes,
                    'formattingMap' => $formattingMap,
                ]);
            });
    }

    public static function getDefaultName(): ?string
    {
        return 'view_files';
    }
}
