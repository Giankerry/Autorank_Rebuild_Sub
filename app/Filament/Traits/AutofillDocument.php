<?php

namespace App\Filament\Traits;

use App\Services\DocumentAiService; //imports for Doc AI and autofill
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Get;
use Filament\Forms\Set; // for getting and setting form values for autofill
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
    protected function getAutofillAction(): Actions
    {
        return Actions::make([
            Action::make('autofill_document')
                ->label('Autofill from Document')
                ->icon('heroicon-s-sparkles')
                ->color('warning')
                ->action(function (Set $set, Get $get) {
                    $files = $get('google_drive_file_id');

                    if (empty($files)) {
                        Notification::make()->title('No File Uploaded')->body('Please upload a document first.')->warning()->send();
                        return;
                    }
                    // find the latest temporary file
                    $fileToProcess = null;
                    foreach (array_reverse($files) as $file) {
                        if ($file instanceof TemporaryUploadedFile) {
                            $fileToProcess = $file;
                            break;
                        }
                    }
                    // if no file found
                    if (!$fileToProcess) {
                        Notification::make()->title('File Not Ready')->body('Please ensure the file upload is complete or try reloading the form.')->warning()->send();
                        return;
                    }

                    try {
                        $docAiService = app(DocumentAiService::class);
                        $extractedData = $docAiService->processDocument($fileToProcess);

                        // Get flags from the service
                        $isCertificate = $extractedData['IsCertificate'] ?? false;
                        $isMoa = $extractedData['IsMoa'] ?? false;
                        $isResearch = $extractedData['IsResearch'] ?? false;
                        $docType = $extractedData['DocumentType'] ?? 'Unknown';


                        if ($isCertificate) {
                            // certificate/diploma checks
                            $credentialType = Str::title(strtolower($extractedData['CredentialType'] ?? null));
                            $dateCompleted = $extractedData['DateCompleted'] ?? $extractedData['YearIssued'] ?? null;
                            $issuingOrg = Str::title(strtolower($extractedData['IssuingOrganization'] ?? null));
                            $venue = Str::title(strtolower($extractedData['AwardVenue'] ?? null));

                            $this->mapCertificateDataToForm($set, $get, $credentialType, $dateCompleted, $issuingOrg, $venue);
                            Notification::make()->title('AI Extraction Successful')->body('Document details were extracted and autofilled.')->success()->send();
                        } elseif ($isMoa) {
                            //check if moa
                            $partnerName = Str::title(strtolower($extractedData['PartnerName'] ?? null));
                            $startDate = $extractedData['DateOfEffectivity'] ?? null;
                            $expirationDate = $extractedData['ExpirationDate'] ?? null;
                            $scope = $extractedData['Scope'] ?? null;

                            $this->mapMoaDataToForm($set, $get, $partnerName, $startDate, $expirationDate, $scope);
                            Notification::make()->title('AI Extraction Successful')->body('Agreement details were extracted and autofilled.')->success()->send();
                        } elseif ($isResearch) {
                            // check if research/thesis
                            $title = $extractedData['Title'] ?? null;
                            $authorList = $extractedData['AuthorList'] ?? null;
                            $publisher = Str::title(strtolower($extractedData['Publisher'] ?? null));
                            $datePublished = $extractedData['DatePublished'] ?? null;

                            $this->mapResearchDataToForm($set, $get, $title, $authorList, $publisher, $datePublished, $docType);
                            Notification::make()->title('AI Extraction Successful')->body('Research/Thesis details were extracted and autofilled.')->success()->send();
                        } else {
                            // document type not recognized fail message
                            Log::warning("Autofill failed: Document type '{$docType}' is not supported by this form.");
                            Notification::make()
                                ->title('Document Not Supported')
                                ->body("The document type '{$docType}' is not supported for autofill in this form.")
                                ->warning()
                                ->send();
                        }
                    } catch (\Exception $e) { // general error catch
                        Log::error("Document AI Button Error: " . $e->getMessage());
                        Notification::make()
                            ->title('Document AI Error')
                            ->body('The document uploaded could be invalid or surpassed the number of pages allowed for autofill.')
                            ->danger()
                            ->send();
                    }
                })
                ->extraAttributes([
                    'class' => 'mt-8',
                ])
                ->visible(fn(Get $get) => !empty($get('google_drive_file_id'))),
        ])->columnSpan(1);
    }
}
