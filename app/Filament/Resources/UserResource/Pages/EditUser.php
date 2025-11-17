<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\FacultyRank;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Actions\Action as NotificationAction;
use Illuminate\Notifications\DatabaseNotification;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Automatically update rank assignment details on change
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Get the original faculty rank ID from the record being edited.
        $originalRankId = $this->getRecord()->faculty_rank_id;

        // Check if the faculty rank in the form data is different from the original.
        if ($originalRankId != $data['faculty_rank_id']) {
            // If a rank was added or changed...
            if (filled($data['faculty_rank_id'])) {
                $data['rank_assigned_by'] = Auth::user()->email;
                $data['rank_assigned_at'] = now();
            } else {
                // If the rank was cleared (set to 'Unset')...
                $data['rank_assigned_by'] = null;
                $data['rank_assigned_at'] = null;
            }
        }

        return $data;
    }

    /**
     * Send notification to user after rank is successfully saved.
     */
    protected function afterSave(): void
    {
        $user = $this->getRecord();
        $adminUser = Auth::user();

        $rankName = $user->facultyRank?->name ?? 'Unset';

        if ($user->wasChanged('faculty_rank_id')) {
            $notificationToInstructor = Notification::make()
                ->title('Faculty Rank Updated')
                ->body("Your faculty rank was updated to {$rankName}. Please refresh the page to update your view.")
                ->icon('heroicon-o-academic-cap')
                ->success();

            $notificationToInstructor->sendToDatabase($user);

            $requestUrl = "/admin/users/{$user->id}/edit";

            $jsonUrl = str_replace('/', '\/', $requestUrl);

            DatabaseNotification::query()
                ->where('notifiable_type', 'App\Models\User')
                ->where('notifiable_id', $adminUser->id)
                ->whereNull('read_at')
                ->where('data', 'like', '%"title":"Account Activation Request"%')
                ->where('data', 'like', '%"url":"' . $jsonUrl . '"%')
                ->update(['read_at' => now()]);
        }
    }
}
