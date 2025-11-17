<?php

namespace App\Filament\Traits;

use App\Services\DocumentAiService;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Str;

trait AutofillDocument
{
    abstract protected function mapCertificateDataToForm($set, $get, ?string $credentialType, ?string $dateCompleted, ?string $issuingOrg, ?string $venue): void;

    abstract protected function mapMoaDataToForm($set, $get, ?string $partnerName, ?string $startDate, ?string $expirationDate, ?string $scope): void;

    abstract protected function mapResearchDataToForm($set, $get, ?string $title, ?string $authorList, ?string $publisher, ?string $datePublished, ?string $documentType): void;


    // The general autofill action for all document types
    protected function getAutofillAction(): Group
    {
        $isFileUploaded = fn(Get $get) => !empty($get('google_drive_file_id'));
        $noticeText = '(!) Note : Upload a document first to activate autofill.';
        $noticeColor = fn(Get $get) => $isFileUploaded($get) ? 'text-yellow-600' : 'text-gray-500';

        return Group::make([

            // 1. Button (Appears on top)
            Actions::make([
                Action::make('autofill_document')
                    ->label('Check and Autofill using AI✨')
                    ->icon('heroicon-s-sparkles')

                    ->color(fn(Get $get) => $isFileUploaded($get) ? 'warning' : 'secondary')

                    ->disabled(fn(Get $get) => !$isFileUploaded($get))

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

                            $isCertificate = $extractedData['IsCertificate'] ?? false;
                            $isMoa = $extractedData['IsMoa'] ?? false;
                            $isResearch = $extractedData['IsResearch'] ?? false;
                            $docType = $extractedData['DocumentType'] ?? 'Unknown';


                            if ($isCertificate) {
                                $credentialType = Str::title(strtolower($extractedData['CredentialType'] ?? null));
                                $dateCompleted = $extractedData['DateCompleted'] ?? $extractedData['YearIssued'] ?? null;
                                $issuingOrg = Str::title(strtolower($extractedData['IssuingOrganization'] ?? null));
                                $venue = Str::title(strtolower($extractedData['AwardVenue'] ?? null));

                                // Note: The concrete widget's method MUST also drop the type hints here: $this->mapCertificateDataToForm($set, $get, ...)
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
                            Log::error("Document AI Button Error: " . $e->getMessage());
                            Notification::make()
                                ->title('Document AI Error')
                                ->body('The document uploaded could be invalid or surpassed the number of pages allowed for autofill.')
                                ->danger()
                                ->send();
                        }
                    })
                    ->extraAttributes([
                        'class' => 'w-full', // Ensure button takes full width of its container
                    ]),
            ])->columnSpanFull(),

            // 2. Placeholder (Notice, appears below the button)
            Placeholder::make('')
                ->content($noticeText)
                ->extraAttributes(fn(Get $get) => [
                    'class' => 'text-xs font-semibold px-1 pt-1 text-center ' . $noticeColor($get),
                ]),
        ])
            ->columnSpan(1)
            ->extraAttributes(['class' => 'flex flex-col justify-end h-full']);
    }
}
