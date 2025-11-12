<?php

namespace App\Filament\Traits;

use App\Services\DocumentAiService;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Group; // used to group button and notice
use Filament\Forms\Components\Placeholder; //used for notice
use Filament\Forms\Get; // to get form values
use Filament\Forms\Set; // to set form values
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Str; // supports string formatting

trait AutofillDocument
{
    // Certificate data mapping
    abstract protected function mapCertificateDataToForm(Set $set, Get $get, ?string $credentialType, ?string $dateCompleted, ?string $issuingOrg, ?string $venue): void;

    // abstract for moa mapping
    abstract protected function mapMoaDataToForm(Set $set, Get $get, ?string $partnerName, ?string $startDate, ?string $expirationDate, ?string $scope): void;


    // abstract for research/thesis mapping
    abstract protected function mapResearchDataToForm(Set $set, Get $get, ?string $title, ?string $authorList, ?string $publisher, ?string $datePublished, ?string $documentType): void;


    // The general autofill action for all document types
    protected function getAutofillAction(): Group
    {
        $isFileUploaded = fn(Get $get) => !empty($get('google_drive_file_id'));
        $noticeText = '(!) Note : Upload a document first to activate autofill.';
        $noticeColor = fn(Get $get) => $isFileUploaded($get) ? 'text-yellow-600' : 'text-gray-500';

        return Group::make([
            //initialize the actions component
            Actions::make([ // button content and logic
                Action::make('autofill_document')
                    ->label('Check and Autofill')
                    ->icon('heroicon-s-sparkles')

                    // Dynamic Color
                    ->color(fn(Get $get) => $isFileUploaded($get) ? 'warning' : 'secondary')

                    // Disabes the button when file upload is empty
                    ->disabled(fn(Get $get) => !$isFileUploaded($get))

                    // Action Logic
                    ->action(function (Set $set, Get $get) {
                        $files = $get('google_drive_file_id');

                        if (empty($files)) {
                            Notification::make()->title('No File Uploaded')->body('Please upload a document first.')->warning()->send();
                            return;
                        }

                        $fileToProcess = null;
                        foreach (array_reverse($files) as $file) {
                            if ($file instanceof TemporaryUploadedFile) {
                                $fileToProcess = $file;
                                break;
                            }
                        }

                        if (!$fileToProcess) {
                            Notification::make()->title('File Not Ready')->body('Please ensure the file upload is complete or try reloading the form.')->warning()->send();
                            return;
                        }

                        try {
                            //check document type using Doc ai
                            $docAiService = app(DocumentAiService::class);
                            $extractedData = $docAiService->processDocument($fileToProcess);

                            $isCertificate = $extractedData['IsCertificate'] ?? false;
                            $isMoa = $extractedData['IsMoa'] ?? false;
                            $isResearch = $extractedData['IsResearch'] ?? false;
                            $docType = $extractedData['DocumentType'] ?? 'Unknown';

                            //checks document type and map data accordingly
                            if ($isCertificate) {
                                $credentialType = Str::title(strtolower($extractedData['CredentialType'] ?? null));
                                $dateCompleted = $extractedData['DateCompleted'] ?? $extractedData['YearIssued'] ?? null;
                                $issuingOrg = Str::title(strtolower($extractedData['IssuingOrganization'] ?? null));
                                $venue = Str::title(strtolower($extractedData['AwardVenue'] ?? null));

                                $this->mapCertificateDataToForm($set, $get, $credentialType, $dateCompleted, $issuingOrg, $venue);
                                Notification::make()->title('AI Extraction Successful')->body('Document details were extracted and autofilled.')->success()->send();
                            } elseif ($isMoa) {
                                $partnerName = Str::title(strtolower($extractedData['PartnerName'] ?? null));
                                $startDate = $extractedData['DateOfEffectivity'] ?? null;
                                $expirationDate = $extractedData['ExpirationDate'] ?? null;
                                $scope = $extractedData['Scope'] ?? null;

                                $this->mapMoaDataToForm($set, $get, $partnerName, $startDate, $expirationDate, $scope);
                                Notification::make()->title('AI Extraction Successful')->body('Agreement details were extracted and autofilled.')->success()->send();
                            } elseif ($isResearch) {
                                $title = $extractedData['Title'] ?? null;
                                $authorList = $extractedData['AuthorList'] ?? null;
                                $publisher = Str::title(strtolower($extractedData['Publisher'] ?? null));
                                $datePublished = $extractedData['DatePublished'] ?? null;

                                $this->mapResearchDataToForm($set, $get, $title, $authorList, $publisher, $datePublished, $docType);
                                Notification::make()->title('AI Extraction Successful')->body('Research/Thesis details were extracted and autofilled.')->success()->send();
                            } else {
                                Log::warning("Autofill failed: Document type '{$docType}' is not supported by this form.");
                                Notification::make()
                                    ->title('Document Not Supported')
                                    ->body("The document type '{$docType}' is not supported for autofill in this form.")
                                    ->warning()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            //returns error log and notification
                            Log::error("Document AI Button Error: " . $e->getMessage());
                            Notification::make()
                                ->title('Document AI Error')
                                ->body('The document uploaded could be invalid or surpassed the number of pages allowed for autofill.')
                                ->danger()
                                ->send();
                        }
                    })
                    // increase width
                    ->extraAttributes([
                        'class' => 'w-full',
                    ]),
            ])->columnSpanFull(),

            //placeholder used for notice
            Placeholder::make('')
                ->content($noticeText)
                ->extraAttributes(fn(Get $get) => [
                    'class' => 'text-gray-500 text-xs font-semibold px-1 pt-1 text-center ' . $noticeColor($get),
                ]),
        ])
            ->columnSpan(1)
            // align contents to the bottom of the grid
            ->extraAttributes(['class' => 'flex flex-col justify-end h-full']);
    }
}
