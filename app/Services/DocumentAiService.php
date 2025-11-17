<?php

namespace App\Services;

use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient; // import for Document AI client
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile; //for handling uploaded files
use Illuminate\Support\Str; //import for String functions

class DocumentAiService
{
    protected string $classificationProcessorId;
    protected string $certificateExtractionProcessorId;
    protected string $moaExtractionProcessorId;
    protected string $researchExtractionProcessorId;
    protected string $location;
    protected string $projectId;
    protected string $credentialsPath;

    public function __construct()
    { //instantiation of the service
        $this->projectId = config('services.google.project_id', env('GOOGLE_CLOUD_PROJECT_ID', ''));
        $this->location = config('services.google.location', env('DOCAI_LOCATION', 'us'));
        $this->classificationProcessorId = env('DOCAI_CLASSIFIER_ID', '');

        // Setting Extractor IDs
        $this->certificateExtractionProcessorId = env('DOCAI_EXTRACTOR_ID', '');
        $this->moaExtractionProcessorId = env('DOCAI_MOA_EXTRACTOR_ID', '');
        $this->researchExtractionProcessorId = env('DOCAI_RESEARCH_EXTRACTOR_ID', '');

        //project ID validation
        if (empty($this->projectId)) {
            Log::error("Missing GOOGLE_CLOUD_PROJECT_ID — check .env or config/services.php");
            throw new \RuntimeException("Missing GOOGLE_CLOUD_PROJECT_ID in environment.");
        }

        // resolving credentials path
        $envPath = env('GOOGLE_APPLICATION_CREDENTIALS_JSON', 'google-service-account.json');
        $isAbsolute = Str::startsWith($envPath, ['/', '\\']) || (strlen($envPath) > 1 && $envPath[1] === ':');

        if ($isAbsolute) {
            $this->credentialsPath = $envPath;
        } else {
            $this->credentialsPath = base_path($envPath);
        }
    }
    // Document AI client initialization
    protected function getClient(): DocumentProcessorServiceClient
    {
        return new DocumentProcessorServiceClient([
            'credentials' => $this->credentialsPath
        ]);
    }
    // converts string to pascal case
    protected function toPascalCase(string $string): string
    {
        $snakeCase = Str::snake($string);
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $snakeCase)));
    }

    public function processDocument(TemporaryUploadedFile $file): array
    {
        $content = $file->get();
        $mimeType = $file->getMimeType();

        // Step 1 Classification of document
        $classificationResult = $this->classifyDocument($content, $mimeType);
        $documentType = $classificationResult['DocumentType'] ?? null; // This is already uppercase

        if (is_null($documentType)) {
            return [];
        }

        $result = ['DocumentType' => $documentType];

        // Step 2: Routing - Decide which extractor to use
        $processorIdToUse = null;
        $isCertificate = false;
        $isMoa = false;
        $isResearch = false;

        if (Str::contains($documentType, ['CERTIFICATE', 'DIPLOMA'], true)) {
            $processorIdToUse = $this->certificateExtractionProcessorId;
            $isCertificate = true; // This flag tells the trait it's a certificate-like doc

        } elseif (Str::contains($documentType, ['MOA'], true)) {
            $processorIdToUse = $this->moaExtractionProcessorId;
            $isMoa = true;
        } elseif (Str::contains($documentType, ['RESEARCH', 'THESIS'], true)) {
            $processorIdToUse = $this->researchExtractionProcessorId;
            $isResearch = true;
        }

        // Step 3: Extraction (if a valid route was found)
        if ($processorIdToUse) {
            $extractedEntities = $this->extractEntities($content, $mimeType, $processorIdToUse);
            $result = array_merge($result, $extractedEntities);

            // Set flags for the trait to read
            $result['IsCertificate'] = $isCertificate;
            $result['IsMoa'] = $isMoa;
            $result['IsResearch'] = $isResearch;
        } else {
            // No valid extractor route found
            $result['IsCertificate'] = false;
            $result['IsMoa'] = false;
            $result['IsResearch'] = false;
        }

        return $result;
    }

    // document classification method
    protected function classifyDocument(string $content, string $mimeType): array
    {
        if (empty($this->classificationProcessorId)) { //skip if no classifier is set
            Log::error("Missing DOCAI_CLASSIFIER_ID. Skipping classification.");
            return [];
        }
        // proceed with classification
        $name = "projects/{$this->projectId}/locations/{$this->location}/processors/{$this->classificationProcessorId}";
        $client = $this->getClient();

        try { // send classification request
            $request = (new ProcessRequest())
                ->setName($name)
                ->setRawDocument(
                    (new RawDocument())->setContent($content)->setMimeType($mimeType)
                );

            $response = $client->processDocument($request);
            $document = $response->getDocument();

            $documentType = null;
            $classificationResult = [];
            // extract the primary document type
            foreach ($document->getEntities() as $entity) {
                $documentType = $entity->getType() ?? null;
                break;
            }

            if (!empty($documentType)) {
                $classificationResult['DocumentType'] = Str::upper($documentType); // Ensures uppercase
            }

            Log::info('Classification result', ['DocumentType' => $documentType]);
            return $classificationResult;
        } catch (\Exception $e) {
            Log::error("Document AI Classification Error: " . $e->getMessage());
            return [];
        } finally {
            $client->close();
        }
    }

    // document extraction based on type
    protected function extractEntities(string $content, string $mimeType, string $processorId): array
    {
        if (empty($processorId)) { //skip if no extractor is set
            Log::error("Missing required Extractor ID. Skipping extraction.");
            return [];
        }

        $name = "projects/{$this->projectId}/locations/{$this->location}/processors/{$processorId}";
        $client = $this->getClient();
        // proceed with extraction
        try {
            $request = (new ProcessRequest())
                ->setName($name)
                ->setRawDocument(
                    (new RawDocument())->setContent($content)->setMimeType($mimeType)
                );

            $response = $client->processDocument($request);
            $document = $response->getDocument();
            // extract entities
            $extractedEntities = [];
            foreach ($document->getEntities() as $entity) {
                $key = $this->toPascalCase($entity->getType() ?? 'Unknown');
                $value = $entity->getMentionText() ?? null;
                $extractedEntities[$key] = $value;
            }
            // show extracted entities info log
            Log::info('Extracted entities', $extractedEntities);
            return $extractedEntities;
        } catch (\Exception $e) {
            //returns error log and notification
            Log::error("Document AI Extraction Error: " . $e->getMessage());
            return [];
        } finally {
            // close the client
            $client->close();
        }
    }
}
