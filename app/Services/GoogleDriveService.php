<?php

namespace App\Services;

use App\Models\User;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Http\MediaFileUpload;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\GoogleAccountDisconnectedException;
use Psr\Http\Message\ResponseInterface;

/**
 * Manages all Google Drive API interactions using per-user OAuth 2.0 tokens.
 * This service will be registered as a singleton in AppServiceProvider.
 */
class GoogleDriveService
{
    protected ?Drive $service = null;
    protected ?User $user = null;

    /**
     * Sets the user for this instance of the service.
     *
     * @param User $user The user to authenticate as.
     * @return self
     * @throws GoogleAccountDisconnectedException
     */
    public function forUser(User $user): self
    {
        if ($this->user && $this->user->id === $user->id && $this->service) {
            return $this;
        }

        $this->user = $user;
        $this->service = $this->getClient($user);

        return $this;
    }

    /**
     * Authenticates with Google using the user's stored refresh token.
     *
     * @param User $user
     * @return Drive
     * @throws GoogleAccountDisconnectedException If the user is disconnected or credentials are bad.
     */
    protected function getClient(User $user): Drive
    {
        if (empty($user->google_refresh_token)) {
            throw new GoogleAccountDisconnectedException('This user has not connected their Google Drive account or has revoked permission.');
        }

        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setAccessType('offline');

        try {
            if ($user->google_token) {
                $client->setAccessToken($user->google_token);
            }

            if (!$user->google_token || $client->isAccessTokenExpired()) {
                $newAccessToken = $client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
                $user->update(['google_token' => $newAccessToken]);
            }

            return new Drive($client);
        } catch (\Exception $e) {
            Log::error('Google Drive refresh token failed.', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            $user->update([
                'google_token' => null,
                'google_refresh_token' => null,
            ]);

            throw new GoogleAccountDisconnectedException('Google Drive access has expired or was revoked. Please log out and log in again to reconnect.');
        }
    }

    /**
     * Uploads a file from a Filament FileUpload component into a nested folder structure.
     *
     * @param TemporaryUploadedFile $file The temporary file from Livewire.
     * @param array $folderPath The desired directory hierarchy (e.g., ['KRA I', 'A: Instructional Materials']).
     * @return string The new Google Drive File ID.
     */
    public function uploadFile(TemporaryUploadedFile $file, array $folderPath): string
    {
        if (!$this->service) {
            throw new \Exception('GoogleDriveService must be initialized with forUser() before uploading.');
        }

        $targetFolderId = $this->findOrCreateNestedFolder($folderPath);

        $fileMetadata = new DriveFile([
            'name' => $file->getClientOriginalName(),
            'parents' => [$targetFolderId]
        ]);

        $chunkSizeBytes = 1 * 1024 * 1024;
        $client = $this->service->getClient();
        $client->setDefer(true);

        $request = $this->service->files->create($fileMetadata);

        $media = new MediaFileUpload(
            $client,
            $request,
            $file->getMimeType(),
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize($file->getSize());

        $status = false;
        $handle = fopen($file->getRealPath(), "rb");

        try {
            while (!$status && !feof($handle)) {
                $chunk = fread($handle, $chunkSizeBytes);
                $status = $media->nextChunk($chunk);
            }
            return $status['id'];
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            $client->setDefer(false);
        }
    }

    /**
     * Finds or creates a nested folder structure and returns the ID of the final folder.
     *
     * @param array $folderPath An array of folder names in order (e.g., ['KRA I', 'Sub Folder'])
     * @return string The Folder ID of the last folder in the path.
     */
    public function findOrCreateNestedFolder(array $folderPath): string
    {
        $currentParentId = $this->findOrCreateFolder('Autorank Files');

        foreach ($folderPath as $folderName) {
            $currentParentId = $this->findOrCreateFolder($folderName, $currentParentId);
        }

        return $currentParentId;
    }


    /**
     * Finds a folder by name within a parent, or creates it if it doesn't exist.
     *
     * @param string $folderName
     * @param string|null $parentId (null means root directory)
     * @return string The Folder ID.
     */
    public function findOrCreateFolder(string $folderName, ?string $parentId = null): string
    {
        $query = "mimeType='application/vnd.google-apps.folder' and name='$folderName' and trashed=false";

        if ($parentId) {
            $query .= " and '$parentId' in parents";
        } else {
            $query .= " and 'root' in parents";
        }

        $response = $this->service->files->listFiles([
            'q' => $query,
            'fields' => 'files(id)',
            'spaces' => 'drive',
        ]);

        if (count($response->getFiles()) > 0) {
            return $response->getFiles()[0]->getId();
        }

        $folderMetadata = new DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder'
        ]);

        if ($parentId) {
            $folderMetadata->setParents([$parentId]);
        }

        $folder = $this->service->files->create($folderMetadata, ['fields' => 'id']);
        return $folder->id;
    }

    /**
     * Deletes a file from Google Drive by its ID.
     *
     * @param string $fileId
     * @return void
     */
    public function deleteFile(string $fileId): void
    {
        if (!$this->service) {
            throw new \Exception('GoogleDriveService must be initialized with forUser() before deleting.');
        }

        try {
            $this->service->files->delete($fileId);
        } catch (\Exception $e) {
            if ($e->getCode() == 404) {
                Log::info('Attempted to delete a Google Drive file that was already gone.', ['file_id' => $fileId, 'user_id' => $this->user->id]);
                return;
            }
            throw $e;
        }
    }

    /**
     * Gets the file content as a stream for viewing.
     *
     * @param string $fileId
     * @return array An array containing ['stream' => \Psr\Http\Message\StreamInterface, 'metadata' => DriveFile]
     */
    public function getFileStream(string $fileId): array
    {
        if (!$this->service) {
            throw new \Exception('GoogleDriveService must be initialized with forUser() before viewing.');
        }

        $metadata = $this->service->files->get($fileId, ['fields' => 'mimeType, name']);

        /** @var ResponseInterface $response */
        $response = $this->service->files->get($fileId, ['alt' => 'media']);

        return [
            'stream' => $response->getBody(),
            'metadata' => $metadata,
        ];
    }

    /**
     * Gets the embeddable 'webViewLink' for a file.
     *
     * @param string $fileId
     * @return string|null The embeddable URL or null on failure.
     */
    public function getWebViewLink(string $fileId): ?string
    {
        if (!$this->service) {
            throw new \Exception('GoogleDriveService must be initialized with forUser() before getting a web view link.');
        }

        try {
            $file = $this->service->files->get($fileId, ['fields' => 'webViewLink']);
            return $file->getWebViewLink();
        } catch (\Exception $e) {
            Log::error('Could not get webViewLink for file.', [
                'file_id' => $fileId,
                'user_id' => $this->user?->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
