<?php

namespace App\Filament\Instructor\Widgets;

use App\Filament\Instructor\Resources\ApplicationResource;
use App\Models\User;
use App\Models\FacultyRank;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\DatabaseNotification;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Actions\Action as NotificationAction;

class WelcomeWidget extends Widget implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $view = 'filament.instructor.widgets.welcome-widget';

    protected int | string | array $columnSpan = 'full';

    public ?User $user;
    public bool $isActivated = false;

    public function mount(): void
    {
        $this->user = Auth::user();
        $this->isActivated = !is_null($this->user->faculty_rank_id);
    }

    public function startApplicationAction(): Action
    {
        return Action::make('startApplication')
            ->label('Start New Application')
            ->icon('heroicon-o-plus-circle')
            ->url(ApplicationResource::getUrl('create'));
    }

    public function notifyAdminAction(): Action
    {
        return Action::make('notifyAdmin')
            ->label('Notify Admin for Activation')
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->form([
                Select::make('faculty_rank_id')
                    ->label('What is your Faculty Rank?')
                    ->options(FacultyRank::all()->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText('Please select the faculty rank you should be assigned.')
            ])
            ->action(function (array $data) {
                $instructor = Auth::user();
                $admins = User::role(['Admin', 'Super Admin'])->get();

                if ($admins->isEmpty()) {
                    Notification::make()
                        ->title('No Admins Found')
                        ->body('Could not send notification. Please contact support.')
                        ->danger()
                        ->send();
                    return;
                }

                $requestedRank = FacultyRank::find($data['faculty_rank_id']);
                $rankName = $requestedRank ? $requestedRank->name : 'Unset';

                $editUserUrl = "/admin/users/{$instructor->id}/edit";

                $viewUserAction = NotificationAction::make('view_user')
                    ->label('Review User')
                    ->url($editUserUrl)
                    ->button();

                $notificationBody = "Instructor {$instructor->name} ({$instructor->email}) is requesting activation with the Faculty Rank of [ {$rankName} ].";

                $notification = Notification::make()
                    ->title('Account Activation Request')
                    ->body($notificationBody)
                    ->icon('heroicon-o-user-plus')
                    ->warning()
                    ->actions([$viewUserAction]);

                $notification->sendToDatabase($admins);

                Notification::make()
                    ->title('Notification Sent')
                    ->body('All system administrators have been notified. We\'ll notify you once an Admin has changed your faculty rank.')
                    ->success()
                    ->send();
            });
    }
}
