<?php

namespace App\Filament\Instructor\Widgets\KRA3;

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
use App\Forms\Components\TrimmedIntegerInput;
use App\Tables\Columns\ScoreColumn;
use App\Filament\Traits\HandlesKRAFileUploads;
use App\Tables\Actions\ViewSubmissionFilesAction;

class SocialResponsibilityWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a3.social-responsibility-widget';

    protected function getGoogleDriveFolderPath(): array
    {
        return [$this->getKACategory(), 'B: Service to the Community', 'Social Responsibility'];
    }

    protected function getKACategory(): string
    {
        return 'KRA III';
    }

    protected function getActiveSubmissionType(): string
    {
        return 'social_responsibility';
    }

    protected function getOptionsMaps(): array
    {
        return [
            'role' => [
                'head' => 'Head',
                'participant' => 'Participant',
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        return [
            'Role' => $this->getOptionsMaps()['role'],
            'Activity Date' => 'm/d/Y',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading('Institutional Social Responsibility Submissions')
            ->columns([
                Tables\Columns\TextColumn::make('data.activity_title')->label('Activity Title')->wrap(),
                Tables\Columns\TextColumn::make('data.community_name')->label('Community Name'),
                Tables\Columns\TextColumn::make('data.beneficiary_count')->label('Beneficiaries'),
                Tables\Columns\TextColumn::make('data.role')
                    ->label('Role')
                    ->formatStateUsing(fn(?string $state): string => $this->getOptionsMaps()['role'][$state] ?? Str::title($state ?? ''))
                    ->badge(),
                Tables\Columns\TextColumn::make('data.activity_date')->label('Activity Date')->date('m/d/Y'),
                ScoreColumn::make('score'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add')
                    ->form($this->getFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();
                        $data['application_id'] = $this->selectedApplicationId;
                        $data['category'] = $this->getKACategory();
                        $data['type'] = $this->getActiveSubmissionType();
                        return $data;
                    })
                    ->modalHeading('Submit New Social Responsibility Activity')
                    ->modalWidth('3xl')
                    ->after(fn() => $this->mount()),
            ])
            ->actions([
                ViewSubmissionFilesAction::make(),
                EditAction::make()
                    ->form($this->getFormSchema())
                    ->modalHeading('Edit Social Responsibility Activity')
                    ->modalWidth('3xl')
                    ->visible($this->getActionVisibility()),
                DeleteAction::make()
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
            Textarea::make('data.activity_title')
                ->label('Name of Community Extension Activity')
                ->required()
                ->maxLength(65535)
                ->columnSpanFull(),

            TextInput::make('data.community_name')
                ->label('Name of Community/Sponsoring Organization')
                ->required()
                ->maxLength(255),

            TrimmedIntegerInput::make('data.beneficiary_count')
                ->label('No. of Beneficiaries')
                ->required()
                ->minValue(1),

            Select::make('data.role')
                ->label('Role')
                ->options($this->getOptionsMaps()['role'])
                ->searchable()
                ->required(),

            DatePicker::make('data.activity_date')
                ->label('Activity Date')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now()),

            $this->getKRAFileUploadComponent(),
        ];
    }
}
