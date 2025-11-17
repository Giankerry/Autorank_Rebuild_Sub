<?php

namespace App\Filament\Validator\Resources;

use App\Filament\Validator\Resources\ApplicationResource\Pages;
use App\Models\Application;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Tables\Columns\ScoreColumn;
use Filament\Tables\Filters\SelectFilter;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                Application::query()
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Applicant')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('applicant_current_rank')
                    ->label('Current Rank')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('highest_attainable_rank')
                    ->label('Highest Attainable Rank')
                    ->badge()
                    ->placeholder('Not yet calculated.'),
                ScoreColumn::make('final_score')
                    ->label('Final Score')
                    ->badge()
                    ->placeholder('Not yet calculated.'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Pending Validation' => 'warning',
                        'Validated' => 'success',
                        'Rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted On')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Pending Validation' => 'Pending Validation',
                        'Validated' => 'Validated',
                        'Rejected' => 'Rejected',
                    ])
                    ->default('Pending Validation'),
            ])
            ->actions([
                Tables\Actions\Action::make('validate')
                    ->label('Validate')
                    ->icon('heroicon-o-check-badge')
                    ->url(fn(Application $record): string => static::getUrl('validate', ['record' => $record]))
                    ->visible(fn(Application $record): bool => $record->status === 'Pending Validation'),
            ]);
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
            'validate' => Pages\ValidateApplication::route('/{record}/validate'),
        ];
    }
}
