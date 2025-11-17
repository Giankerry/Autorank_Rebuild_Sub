<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\FacultyRank;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = 'System Management';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user instanceof User && $user->hasRole('Admin')) {
            return $query->whereDoesntHave('roles', function (Builder $query) {
                $query->whereIn('name', ['Super Admin', 'super_admin']);
            });
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->hasRole(['Admin', 'Super Admin']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->disabled(),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->disabled(),
                TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn(string $state): string => Hash::make($state))
                    ->dehydrated(fn(?string $state): bool => filled($state))
                    ->required(fn(string $operation): bool => $operation === 'create')
                    ->visible(fn(string $operation): bool => $operation === 'create')
                    ->rule(
                        Password::min(8)
                            ->letters()
                            ->mixedCase()
                            ->numbers()
                            ->symbols()
                            ->uncompromised()
                    )
                    ->confirmed()
                    ->maxLength(255),
                TextInput::make('password_confirmation')
                    ->password()
                    ->required(fn(string $operation): bool => $operation === 'create')
                    ->visible(fn(string $operation): bool => $operation === 'create')
                    ->dehydrated(false),
                Select::make('faculty_rank_id')
                    ->label('Faculty Rank')
                    ->options(FacultyRank::all()->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Unset'),
                Select::make('role_id')
                    ->label('Role')
                    ->required()
                    ->options(Role::whereNotIn('name', ['Super Admin', 'super_admin'])->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->disabled(fn(?User $record): bool => $record?->hasRole(['Super Admin', 'super_admin']) ?? false)
                    ->afterStateHydrated(function (Select $component, ?User $record) {
                        $component->state($record?->roles->first()?->id);
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('facultyRank.name')
                    ->label('Faculty Rank')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->default('Unset'),
                TextColumn::make('rank_assigned_at')
                    ->sortable()
                    ->formatStateUsing(function (?string $state, User $record): string {
                        return $record->faculty_rank_id ? Carbon::parse($state)->format('M d, Y H:i') : '-';
                    })
                    ->default('Unset'),
                TextColumn::make('rank_assigned_by')
                    ->searchable()
                    ->formatStateUsing(function (?string $state, User $record): string {
                        return $record->faculty_rank_id ? $state : '-';
                    })
                    ->default('Unset'),
                TextColumn::make('roles.name')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
