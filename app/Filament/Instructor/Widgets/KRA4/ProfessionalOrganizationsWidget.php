<?php

namespace App\Filament\Instructor\Widgets\KRA4;

use App\Models\Submission;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use App\Filament\Instructor\Widgets\BaseKRAWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Tables\Columns\ScoreColumn;
use App\Filament\Traits\HandlesKRAFileUploads;
use App\Tables\Actions\ViewSubmissionFilesAction;

class ProfessionalOrganizationsWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a4.professional-organizations-widget';

    protected function getGoogleDriveFolderPath(): array
    {
        return [$this->getKACategory(), 'A: Professional Organizations'];
    }

    protected function getKACategory(): string
    {
        return 'KRA IV';
    }

    protected function getActiveSubmissionType(): string
    {
        return 'profdev-organization';
    }

    protected function getOptionsMaps(): array
    {
        return [
            'role' => [
                'board_member' => 'Board Member',
                'officer' => 'Officer',
                'lead_organizer' => 'Lead Organizer',
                'co_organizer' => 'Co-organizer',
                'committee_chair' => 'Committee Chair',
                'committee_member' => 'Committee Member',
                'moderator' => 'Moderator',
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        return [
            'Role' => $this->getOptionsMaps()['role'],
            'Date Activity' => 'm/d/Y',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading('Involvement in Professional Organizations')
            ->columns([
                Tables\Columns\TextColumn::make('data.name')->label('Name of Organization')->wrap(),
                Tables\Columns\TextColumn::make('data.type')->label('Type of Organization')->badge(),
                Tables\Columns\TextColumn::make('data.role')
                    ->label('Role/Contribution')
                    ->formatStateUsing(fn(?string $state): string => $this->getOptionsMaps()['role'][$state] ?? Str::of($state)->replace('_', ' ')->title())
                    ->badge()
                    ->wrap(),
                Tables\Columns\TextColumn::make('data.date_activity')->label('Date of Activity')->date('m/d/Y'),
                ScoreColumn::make('score'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add')
                    ->form($this->getFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();
                        $data['application_id'] = $this->selectedApplicationId;
                        $data['category'] = $this->getKACategory();
                        $data['type'] = $this->getActiveSubmissionType();
                        return $data;
                    })
                    ->modalHeading('Submit New Involvement in Professional Organization')
                    ->modalWidth('3xl')
                    ->after(fn() => $this->mount()),
            ])
            ->actions([
                ViewSubmissionFilesAction::make(),
                Tables\Actions\EditAction::make()
                    ->form($this->getFormSchema())
                    ->modalHeading('Edit Involvement in Professional Organization')
                    ->modalWidth('3xl')
                    ->visible($this->getActionVisibility()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn() => $this->mount())
                    ->visible($this->getActionVisibility()),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return Submission::query()
            ->where('user_id', Auth::id())
            ->where('category', $this->getKACategory())
            ->where('type', $this->getActiveSubmissionType())
            ->where('application_id', $this->selectedApplicationId);
    }

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('data.name')
                ->label('Name of Organization')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            TextInput::make('data.type')
                ->label('Type of Organization (e.g., Education)')
                ->required()
                ->maxLength(255),
            DatePicker::make('data.date_activity')
                ->label('Date of Activity')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now()),
            Textarea::make('data.activity')
                ->label('Activity of the Organization Participated by the Faculty')
                ->required()
                ->maxLength(65535)
                ->columnSpanFull(),
            Select::make('data.role')
                ->label('Role or Contribution to the Activity')
                ->options($this->getOptionsMaps()['role'])
                ->required()
                ->searchable()
                ->columnSpanFull(),

            $this->getKRAFileUploadComponent(),
        ];
    }
}
