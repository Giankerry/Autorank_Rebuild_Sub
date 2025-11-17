<?php

namespace App\Filament\Instructor\Resources;

use App\Filament\Instructor\Resources\ApplicationResource\Pages;
use App\Filament\Instructor\Resources\ApplicationResource\RelationManagers;
use App\Models\Application;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use App\Tables\Columns\ScoreColumn;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use App\Models\PromotionCycle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use App\Services\ApplicationScoringService;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Component;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    public static function canCreate(): bool
    {
        return !is_null(Auth::user()->faculty_rank_id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('evaluation_cycle')
                    ->label('Promotion Cycle')
                    ->options(
                        PromotionCycle::where('is_active', true)->pluck('name', 'name')
                    )
                    ->searchable()
                    ->required(),
                Textarea::make('remarks')
                    ->label('Validator Remarks')
                    ->columnSpanFull()
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('evaluation_cycle')
                    ->label('Cycle')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Pending Validation' => 'warning',
                        'Validated' => 'success',
                        'Rejected' => 'danger',
                        default => 'primary',
                    }),
                ScoreColumn::make('final_score')
                    ->label('Final Score')
                    ->placeholder('Not yet calculated.'),
                Tables\Columns\TextColumn::make('highest_attainable_rank')
                    ->label('Highest Attainable Rank')
                    ->badge()
                    ->placeholder('Not yet calculated.'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M j, Y g:ia')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    // Action::make('view')
                    //     ->label('View on Dashboard')
                    //     ->icon('heroicon-o-eye')
                    //     ->color('gray')
                    //     ->url(function (Application $record) {
                    //         session()->put('selected_app_id', $record->id);
                    //         session()->save();
                    //         return filament()->getUrl();
                    //     }),
                    EditAction::make()
                        ->visible(fn(Application $record): bool => $record->status === 'Draft'),
                    Action::make('submitForValidation')
                        ->label('Submit for Validation')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Submit Application')
                        ->modalDescription('This will calculate the final score and submit your application. You will no longer be able to edit it. Are you sure?')
                        ->visible(fn(Application $record): bool => $record->status === 'Draft')
                        ->action(function (Application $record, ApplicationScoringService $appScoringService, Component $livewire) {
                            $appScoringService->calculateScore($record);
                            $record->applicant_current_rank = $record->user->facultyRank?->name ?? 'N/A';
                            $record->status = 'Pending Validation';
                            $record->save();
                            $record->refresh();
                            Notification::make()
                                ->title('Application Submitted!')
                                ->body("Your final score is {$record->final_score}. It is now pending validation.")
                                ->success()
                                ->send();

                            $livewire->dispatch('refresh');
                        }),
                    DeleteAction::make()
                        ->visible(fn(Application $record): bool => $record->status === 'Draft'),
                ])
            ]);
        // ->bulkActions([
        //     Tables\Actions\BulkActionGroup::make([
        //         Tables\Actions\DeleteBulkAction::make(),
        //     ]),
        // ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApplications::route('/'),
            'create' => Pages\CreateApplication::route('/create'),
            'edit' => Pages\EditApplication::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id());
    }
}
