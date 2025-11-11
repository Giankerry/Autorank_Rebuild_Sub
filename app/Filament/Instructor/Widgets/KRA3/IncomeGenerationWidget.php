<?php

namespace App\Filament\Instructor\Widgets\KRA3;

use App\Models\Submission;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Instructor\Widgets\BaseKRAWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Forms\Components\TrimmedNumericInput;
use App\Tables\Columns\ScoreColumn;
use Filament\Forms\Get;
use App\Filament\Traits\HandlesKRAFileUploads;
use App\Tables\Actions\ViewSubmissionFilesAction;

class IncomeGenerationWidget extends BaseKRAWidget
{
    use HandlesKRAFileUploads;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.instructor.widgets.k-r-a3.income-generation-widget';

    protected function getGoogleDriveFolderPath(): array
    {
        return [$this->getKACategory(), 'A: Service to the Institution', 'Income Generation'];
    }

    protected function getKACategory(): string
    {
        return 'KRA III';
    }

    protected function getActiveSubmissionType(): string
    {
        return 'extension-income-generation';
    }

    protected function getOptionsMaps(): array
    {
        return [
            'role' => [
                'lead_contributor' => 'Lead Contributor',
                'co_contributor' => 'Co-contributor',
            ],
        ];
    }

    public function getDisplayFormattingMap(): array
    {
        return [
            'Role' => $this->getOptionsMaps()['role'],
            'Coverage Start' => 'm/d/Y',
            'Coverage End' => 'm/d/Y',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->getTableQuery())
            ->heading('Contribution to Income Generation Submissions')
            ->columns([
                Tables\Columns\TextColumn::make('data.name')->label('Name of Product/Project')->wrap(),
                Tables\Columns\TextColumn::make('data.role')
                    ->label('Role')
                    ->formatStateUsing(fn(?string $state): string => $this->getOptionsMaps()['role'][$state] ?? Str::of($state)->replace('_', ' ')->title())
                    ->badge(),
                Tables\Columns\TextColumn::make('data.amount')
                    ->label('Total Amount')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('₱'),
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
                    ->modalHeading('Submit New Income Generation Contribution')
                    ->modalWidth('3xl')
                    ->after(fn() => $this->mount()),
            ])
            ->actions([
                ViewSubmissionFilesAction::make(),
                Tables\Actions\EditAction::make()
                    ->form($this->getFormSchema())
                    ->modalHeading('Edit Income Generation Contribution')
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
            ->where('type', $this->getActiveSubmissionType())
            ->where('application_id', $this->selectedApplicationId);
    }

    protected function getFormSchema(): array
    {
        return [
            Textarea::make('data.name')
                ->label('Name of the Commercialized Product, Funded Project, or Project with Industry')
                ->required()
                ->maxLength(65535)
                ->columnSpanFull(),
            Select::make('data.role')
                ->label('Role')
                ->options($this->getOptionsMaps()['role'])
                ->searchable()
                ->required(),
            TrimmedNumericInput::make('data.amount')
                ->label('Total Amount (Actual Income)')
                ->prefix('₱')
                ->required()
                ->minValue(0),
            DatePicker::make('data.coverage_start')
                ->label('Coverage Period Start')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->maxDate(now())
                ->live(),
            DatePicker::make('data.coverage_end')
                ->label('Coverage Period End')
                ->native(false)
                ->displayFormat('m/d/Y')
                ->required()
                ->minDate(fn(Get $get) => $get('data.coverage_start')),

            $this->getKRAFileUploadComponent(),
        ];
    }
}
