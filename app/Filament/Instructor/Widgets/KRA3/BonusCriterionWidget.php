<?php

namespace App\Filament\Instructor\Widgets\KRA3;

use App\Models\Submission;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
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

class BonusCriterionWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a3.bonus-criterion-widget';

    protected function getGoogleDriveFolderPath(): array
    {
        return [$this->getKACategory(), 'D: Bonus Criterion'];
    }

    protected function isMultipleSubmissionAllowed(): bool
    {
        return false;
    }

    protected function getKACategory(): string
    {
        return 'KRA III';
    }

    protected function getActiveSubmissionType(): string
    {
        return 'extension-bonus-designation';
    }

    protected function getOptionsMaps(): array
    {
        return [
            'designation' => [
                'president_oic' => 'President or OIC President',
                'vice_president' => 'Vice-President',
                'chancellor' => 'Chancellor',
                'vice_chancellor' => 'Vice-Chancellor',
                'campus_director' => 'Campus Director/Administrator/Head',
                'faculty_regent' => 'Faculty Regent',
                'office_director' => 'Office Director',
                'university_college_secretary' => 'University/College Secretary',
                'dean' => 'Dean',
                'associate_dean' => 'Associate Dean',
                'project_head_kra3d' => 'Project Head',
                'department_head' => 'Department Head',
                'institution_committee_chair' => 'Institution-level Committee Chair',
                'institution_committee_member' => 'Institution-level Committee Member',
                'college_secretary' => 'College Secretary',
                'program_chair' => 'Program Chair/Project Head',
                'department_committee_chair' => 'Department-level Committee Chair',
                'department_committee_member' => 'Department-level Committee Member',
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        return [
            'Designation' => $this->getOptionsMaps()['designation'],
            'Period Start' => 'm/d/Y',
            'Period End' => 'm/d/Y',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading('Administrative Designations')
            ->columns([
                Tables\Columns\TextColumn::make('data.designation')
                    ->label('Designation')
                    ->formatStateUsing(fn(?string $state): string => $this->getOptionsMaps()['designation'][$state] ?? Str::of($state)->replace('_', ' ')->title())
                    ->badge()
                    ->wrap(),
                Tables\Columns\TextColumn::make('data.period_start')->label('Effectivity Start')->date('m/d/Y'),
                Tables\Columns\TextColumn::make('data.period_end')->label('Effectivity End')->date('m/d/Y'),
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
                    ->modalHeading('Submit Administrative Designation')
                    ->modalWidth('2xl')
                    ->hidden(fn(): bool => $this->submissionExistsForCurrentType())
                    ->after(fn() => $this->mount()),
            ])
            ->actions([
                ViewSubmissionFilesAction::make(),
                Tables\Actions\EditAction::make()
                    ->form($this->getFormSchema())
                    ->modalHeading('Edit Administrative Designation')
                    ->modalWidth('2xl')
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
            Select::make('data.designation')
                ->label('Designation')
                ->options($this->getOptionsMaps()['designation'])
                ->required()
                ->searchable()
                ->columnSpanFull(),
            DatePicker::make('data.period_start')
                ->label('Effectivity Period Start')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now())
                ->live(),
            DatePicker::make('data.period_end')
                ->label('Effectivity Period End')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->minDate(fn(Get $get) => $get('data.period_start')),

            $this->getKRAFileUploadComponent(),
        ];
    }
}
