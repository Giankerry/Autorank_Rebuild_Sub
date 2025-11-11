<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Exceptions\GoogleAccountDisconnectedException;
use Illuminate\Http\Response;
use App\Models\User;

class FileViewController extends Controller
{
    /**
     * Handle the request to view/download a file from Google Drive.
     *
     * @param GoogleDriveService $driveService
     * @param Submission $submission
     * @param string $fileKey The JSON key (0, 1, 2...) of the file in the google_drive_file_id array.
     * @return StreamedResponse|Response
     */
    public function streamFile(GoogleDriveService $driveService, Submission $submission, string $fileKey)
    {
        $user = Auth::user();
        /** @var \App\Models\User $user */

        $fileOwner = $submission->user;

        if (!$user->is($fileOwner) && !$user->hasRole('Validator')) {
            abort(403, 'You do not have permission to view this file.');
        }

        $fileIds = $submission->google_drive_file_id;
        if (!is_array($fileIds) || !isset($fileIds[$fileKey])) {
            abort(404, 'File not found in this submission.');
        }
        $fileId = $fileIds[$fileKey];

        try {
            $fileData = $driveService->forUser($fileOwner)->getFileStream($fileId);

            $stream = $fileData['stream'];
            $metadata = $fileData['metadata'];
            $mimeType = $metadata->getMimeType();
            $fileName = $metadata->getName();

            return new StreamedResponse(function () use ($stream) {
                while (!$stream->eof()) {
                    echo $stream->read(8192);
                    flush();
                }
            }, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            ]);
        } catch (GoogleAccountDisconnectedException $e) {
            return response("Could not retrieve file: The file owner's Google Account is disconnected or has revoked permission.", 403);
        } catch (\Exception $e) {
            if ($e->getCode() == 404) {
                return response('File not found on Google Drive. It may have been deleted.', 404);
            }
            report($e);
            return response('Could not retrieve file due to a server error.', 500);
        }
    }
}
