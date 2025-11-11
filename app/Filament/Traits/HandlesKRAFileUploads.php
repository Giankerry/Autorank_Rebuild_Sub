<?php

namespace App\Filament\Traits;

use App\Services\GoogleDriveService;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Exceptions\GoogleAccountDisconnectedException;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

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
                /**
                 * @var \App\Filament\Instructor\Widgets\BaseKRAWidget $livewire
                 */
                try {
                    $service = app(GoogleDriveService::class)->forUser(Auth::user());
                    $folderPath = $livewire->getGoogleDriveFolderPath();
                    return $service->uploadFile($file, $folderPath);
                } catch (GoogleAccountDisconnectedException $e) {
                    Notification::make()
                        ->title('Google Drive Error')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    throw new \Exception('Google Drive Error: ' . $e->getMessage());
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Upload Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    throw new \Exception('Upload Failed: ' . $e->getMessage());
                }
            })
            ->deleteUploadedFileUsing(function (string $fileId, $livewire) {
                try {
                    /** @var \App\Models\Submission $record */
                    $record = $livewire->getRecord();
                    $fileOwner = $record->user;

                    $service = app(GoogleDriveService::class)->forUser($fileOwner);
                    $service->deleteFile($fileId);
                } catch (GoogleAccountDisconnectedException $e) {
                    Notification::make()
                        ->title('Deletion Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                    throw new \Exception($e->getMessage());
                } catch (\Exception $e) {
                    if ($e->getCode() !== 404) {
                        Notification::make()
                            ->title('Deletion Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        throw $e;
                    }
                }
            })
            ->columnSpanFull();
    }
}
