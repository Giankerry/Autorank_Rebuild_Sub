<?php

namespace App\Filament\Traits;

use App\Services\DocumentAiService;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait AutofillDocument
{
    //bstract method implemented by the widget to map certificate-related data
    abstract protected function mapCertificateDataToForm(Set $set, Get $get, ?string $credentialType, ?string $dateCompleted, ?string $issuingOrg, ?string $venue): void;

    //Abstract method implemented by the widget to map MOA-related data
    abstract protected function mapMoaDataToForm(Set $set, Get $get, ?string $partnerName, ?string $startDate, ?string $expirationDate, ?string $scope): void;

    //abstract method implemented by the widget to map research/thesis data

    abstract protected function mapResearchDataToForm(Set $set, Get $get, ?string $title, ?string $authorList, ?string $publisher, ?string $datePublished, ?string $documentType): void;


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
                        $docAiService = app(DocumentAiService::class);
                        $extractedData = $docAiService->processDocument($fileToProcess);

                        if ($extractedData['IsCertificate'] ?? false) {
                            // check if doc is certificate
                            $credentialType = $extractedData['CredentialType'] ?? null;
                            $dateCompleted = $extractedData['DateCompleted'] ?? $extractedData['YearIssued'] ?? null;
                            $issuingOrg = $extractedData['IssuingOrganization'] ?? null;
                            $venue = $extractedData['AwardVenue'] ?? null;

                            $this->mapCertificateDataToForm($set, $get, $credentialType, $dateCompleted, $issuingOrg, $venue);
                            Notification::make()->title('AI Extraction Successful')->body('Certificate details were extracted and autofilled.')->success()->send();
                        } elseif ($extractedData['IsMoa'] ?? false) {
                            // check if doc is moa
                            $partnerName = $extractedData['PartnerName'] ?? null;
                            $startDate = $extractedData['DateOfEffectivity'] ?? null;
                            $expirationDate = $extractedData['ExpirationDate'] ?? null;
                            $scope = $extractedData['Scope'] ?? null;
                            $this->mapMoaDataToForm($set, $get, $partnerName, $startDate, $expirationDate, $scope);
                            Notification::make()->title('AI Extraction Successful')->body('Agreement details were extracted and autofilled.')->success()->send();
                            //check if doc is research/thesis
                        } elseif ($extractedData['IsResearch'] ?? false) {
                            $title = $extractedData['Title'] ?? null;
                            $authorList = $extractedData['AuthorList'] ?? null;
                            $publisher = $extractedData['Publisher'] ?? null;
                            $datePublished = $extractedData['DatePublished'] ?? null;
                            $documentType = $extractedData['DocumentType'] ?? null;

                            $this->mapResearchDataToForm($set, $get, $title, $authorList, $publisher, $datePublished, $documentType);
                            Notification::make()->title('AI Extraction Successful')->body('Research/Thesis details were extracted and autofilled.')->success()->send();
                        } else {
                            $docType = $extractedData['DocumentType'] ?? 'Unknown';
                            Log::warning("Autofill failed: Document type '{$docType}' is not supported by this form.");
                            Notification::make()
                                ->title('Document Not Supported')
                                ->body('The uploaded document type is not supported for autofill in this form.')
                                ->warning()
                                ->send();
                        }
                    } catch (\Exception $e) {
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
