<?php

namespace App\Filament\Traits;

use App\Services\GoogleDriveService;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Exceptions\GoogleAccountDisconnectedException;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;
use Illuminate\Support\Facades\Log;

trait HandlesKRAFileUploads
{
    /**
     * Returns a FileUpload component configured for Google Drive
     * using our new per-user GoogleDriveService.
     */
    protected function getKRAFileUploadComponent(): FileUpload
    {
        return FileUpload::make('google_drive_file_id')
            ->label('Proof Document(s)')
            ->helperText('Upload proof documents (PDF, DOCX, XLSX, JPG, PNG, or ZIP, max 10MB each).')
            ->multiple()
            ->reorderable()
            ->required()
            ->maxSize(10240) // 10MB
            ->acceptedFileTypes([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
                'application/msword', // .doc
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
                'application/vnd.ms-excel', // .xls
                'application/zip',
            ])
            ->preserveFilenames()
            ->openable()
            ->downloadable()
            ->hidden(fn(string $operation): bool => $operation === 'edit')
            ->getUploadedFileUsing(function (FileUpload $component, string $fileId): ?array {
                $record = $component->getRecord();

                if (!$record) {
                    return null;
                }

                $fileKey = array_search($fileId, $record->google_drive_file_id ?? []);

                if ($fileKey === false) {
                    return null;
                }

                return [
                    'name' => 'File ' . ($fileKey + 1),
                    'size' => 0,
                    'type' => null,
                    'url'  => route('submission.file.view', [
                        'submission' => $record->id,
                        'fileKey'    => $fileKey,
                    ]),
                ];
            })
            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, $livewire): string {
                set_time_limit(300);

                /**
                 * @var \App\Filament\Instructor\Widgets\BaseKRAWidget $livewire
                 * @var \App\Models\User $user
                 */
                $user = Auth::user();

                if (!$user || !$user->hasDriveScope()) {
                    Notification::make()
                        ->title('Google Drive Permission Required')
                        ->body('You must grant Google Drive permissions to your account before uploading files.')
                        ->danger()
                        ->persistent()
                        ->actions([
                            NotificationAction::make('Go to Settings')
                                ->url(route('filament.instructor.pages.google-settings'))
                                ->button(),
                        ])
                        ->send();

                    throw new \Exception('Google Drive permission missing. Please check your settings.');
                }

                try {
                    $service = app(GoogleDriveService::class)->forUser($user);
                    $folderPath = $livewire->getGoogleDriveFolderPath();
                    return $service->uploadFile($file, $folderPath);
                } catch (GoogleAccountDisconnectedException $e) {
                    Notification::make()
                        ->title('Google Drive Error')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->actions([
                            NotificationAction::make('Go to Settings')
                                ->url(route('filament.instructor.pages.google-settings'))
                                ->button(),
                        ])
                        ->send();

                    throw new \Exception($e->getMessage());
                } catch (\Exception $e) {
                    $message = $e->getMessage();
                    $body = $message;

                    if (str_contains($message, 'insufficient authentication scopes') || str_contains($message, 'insufficientPermissions')) {
                        $title = 'Google Drive Permission Required';
                        $body = 'The application is missing the required permissions for Google Drive. Please re-authenticate in settings.';
                    } else {
                        $title = 'Upload Failed';
                        Log::error('Google Drive Upload Error: ' . $message);
                    }

                    Notification::make()
                        ->title($title)
                        ->body($body)
                        ->danger()
                        ->persistent()
                        ->actions([
                            NotificationAction::make('Go to Settings')
                                ->url(route('filament.instructor.pages.google-settings'))
                                ->button(),
                        ])
                        ->send();

                    throw new \Exception('Upload failed: ' . $title);
                }
            })
            ->deleteUploadedFileUsing(function (string $fileId, $livewire) {
                try {
                    /** @var \App\Models\Submission $record */
                    $record = $livewire->getRecord();
                    $fileOwner = $record ? $record->user : Auth::user();

                    if (!$fileOwner || !$fileOwner->hasDriveScope()) {
                        return;
                    }

                    $service = app(GoogleDriveService::class)->forUser($fileOwner);
                    $service->deleteFile($fileId);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete file from Google Drive: ' . $e->getMessage());
                }
            })
            ->columnSpanFull();
    }
}
