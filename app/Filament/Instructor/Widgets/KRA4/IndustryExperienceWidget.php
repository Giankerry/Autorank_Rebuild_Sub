<?php

namespace App\Filament\Instructor\Widgets\KRA4;

use App\Models\Application;
use App\Models\Submission;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
use App\Forms\Components\TrimmedNumericInput;
use App\Tables\Columns\ScoreColumn;
use App\Filament\Traits\HandlesKRAFileUploads;
use App\Tables\Actions\ViewSubmissionFilesAction;

class IndustryExperienceWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a4.industry-experience-widget';

    public function getGoogleDriveFolderPath(): array
    {
        return [$this->getKACategory(), 'D: Bonus Criterion', 'Industry Experience'];
    }

    protected function getKACategory(): string
    {
        return 'KRA IV';
    }

    protected function getActiveSubmissionType(): string
    {
        return 'profdev-industry-experience';
    }

    protected function getOptionsMaps(): array
    {
        return [
            'designation' => [
                'managerial_supervisory' => 'Managerial/Supervisory',
                'technical_skilled' => 'Technical/Skilled',
                'support_administrative' => 'Support/Administrative',
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
            ->heading('Industry Experience (Non-Academic)')
            ->columns([
                Tables\Columns\TextColumn::make('data.org_name')->label('Company/Organization')->wrap(),
                Tables\Columns\TextColumn::make('data.designation')
                    ->label('Designation/Position')
                    ->formatStateUsing(fn(?string $state): string => $this->getOptionsMaps()['designation'][$state] ?? Str::of($state)->replace('_', ' ')->title())
                    ->badge(),
                Tables\Columns\TextColumn::make('data.no_of_years')->label('No. of Years'),
                Tables\Columns\TextColumn::make('data.period_start')->label('Period Start')->date('m/d/Y'),
                Tables\Columns\TextColumn::make('data.period_end')->label('Period End')->date('m/d/Y'),
                ScoreColumn::make('score'),
            ])
            ->headerActions($this->getTableHeaderActions())
            ->actions($this->getTableActions())
            ->paginated(!$this->validation_mode)
            ->emptyStateHeading($this->getTableEmptyStateHeading())
            ->emptyStateDescription($this->getTableEmptyStateDescription());
    }

    protected function getTableQuery(): Builder
    {
        if ($this->validation_mode) {
            return Submission::query()
                ->where('application_id', $this->record->id)
                ->where('type', $this->getActiveSubmissionType());
        }

        return Submission::query()
            ->where('user_id', Auth::id())
            ->where('type', $this->getActiveSubmissionType())
            ->where('application_id', $this->selectedApplicationId);
    }


    protected function getFormSchema(): array
    {
        return [
            TextInput::make('data.org_name')
                ->label('Name of Company/Organization')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Select::make('data.designation')
                ->label('Designation/Position')
                ->options($this->getOptionsMaps()['designation'])
                ->required()
                ->searchable(),
            DatePicker::make('data.period_start')
                ->label('Period Covered Start')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now())
                ->live(),
            DatePicker::make('data.period_end')
                ->label('Period Covered End')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->minDate(fn(Get $get) => $get('data.period_start'))
                ->live(),
            TrimmedNumericInput::make('data.no_of_years')
                ->label('Number of Years')
                ->step('0.01')
                ->required()
                ->minValue(0)
                ->helperText('Please ensure this matches the duration from the Period Covered dates.')
                ->rules([
                    function (Get $get) {
                        return function (string $attribute, $value, Closure $fail) use ($get) {
                            $startDateStr = $get('data.period_start');
                            $endDateStr = $get('data.period_end');
                            $enteredYears = (float) $value;

                            if ($startDateStr && $endDateStr) {
                                try {
                                    $startDate = Carbon::parse($startDateStr);
                                    $endDate = Carbon::parse($endDateStr);

                                    $calculatedYears = $startDate->diffInDays($endDate->addDay()) / 365.25;

                                    $tolerance = 0.02;

                                    if (abs($calculatedYears - $enteredYears) > $tolerance) {
                                        $fail("The 'Number of Years' entered ({$enteredYears}) does not closely match the calculated duration (" . number_format($calculatedYears, 2) . " years) based on the start and end dates.");
                                    }
                                } catch (\Exception $e) {
                                }
                            }
                        };
                    }
                ]),

            $this->getKRAFileUploadComponent(),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\CreateAction::make()
                ->label('Add')
                ->form($this->getFormSchema())
                ->disabled(function () {
                    $application = Application::find($this->selectedApplicationId);
                    if (!$application) {
                        return true;
                    }
                    return $application->status !== 'Draft';
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['user_id'] = Auth::id();
                    $data['application_id'] = $this->selectedApplicationId;
                    $data['category'] = $this->getKACategory();
                    $data['type'] = $this->getActiveSubmissionType();
                    return $data;
                })
                ->modalHeading('Submit Industry Experience Record')
                ->modalWidth('3xl')
                ->hidden($this->validation_mode)
                ->after(fn() => $this->mount()),
        ];
    }

    protected function getTableActions(): array
    {
        if ($this->validation_mode) {
            return [
                $this->getViewFilesAction(),
                $this->getValidateSubmissionAction(),
            ];
        }

        return [
            ViewSubmissionFilesAction::make(),
            Tables\Actions\EditAction::make()
                ->form($this->getFormSchema())
                ->modalHeading('Edit Industry Experience Record')
                ->modalWidth('3xl')
                ->visible($this->getActionVisibility()),
            Tables\Actions\DeleteAction::make()
                ->after(fn() => $this->mount())
                ->visible($this->getActionVisibility()),
        ];
    }
}
