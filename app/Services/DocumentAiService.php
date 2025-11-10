<?php

namespace App\Services;

use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Str;

class DocumentAiService
{
    protected string $classificationProcessorId;
    protected string $certificateExtractionProcessorId; // Renamed to clarify
    protected string $moaExtractionProcessorId;       // NEW: MOA Extractor ID
    protected string $location;
    protected string $projectId;
    protected string $credentialsPath;

    public function __construct()
    {
        $this->projectId = config('services.google.project_id', env('GOOGLE_CLOUD_PROJECT_ID', ''));
        $this->location = config('services.google.location', env('DOCAI_LOCATION', 'us'));
        $this->classificationProcessorId = env('DOCAI_CLASSIFIER_ID', '');

        // Renaming/Setting Extractor IDs
        $this->certificateExtractionProcessorId = env('DOCAI_EXTRACTOR_ID', ''); // Assuming DOCAI_EXTRACTOR_ID is for certificates
        $this->moaExtractionProcessorId = env('DOCAI_MOA_EXTRACTOR_ID', '');   // NEW: For MOAs

        //project ID validation
        if (empty($this->projectId)) {
            Log::error("Missing GOOGLE_CLOUD_PROJECT_ID — check .env or config/services.php");
            throw new \RuntimeException("Missing GOOGLE_CLOUD_PROJECT_ID in environment.");
        }

        // resolving credentials path
        $envPath = env('GOOGLE_APPLICATION_CREDENTIALS', 'google-service-account.json');
        $isAbsolute = Str::startsWith($envPath, ['/', '\\']) || (strlen($envPath) > 1 && $envPath[1] === ':');

        if ($isAbsolute) {
            $this->credentialsPath = $envPath;
        } else {
            $this->credentialsPath = base_path($envPath);
        }
    }

    protected function getClient(): DocumentProcessorServiceClient
    {
        return new DocumentProcessorServiceClient([
            'credentials' => $this->credentialsPath
        ]);
    }

    // Standardize entity keys to PascalCase (no change needed here, it handles both MOA and Cert fields)
    protected function toPascalCase(string $string): string
    {
        $snakeCase = Str::snake($string);
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $snakeCase)));
    }

    public function processDocument(TemporaryUploadedFile $file): array
    {
        $content = $file->get();
        $mimeType = $file->getMimeType();

        // Step 1: Classification
        $classificationResult = $this->classifyDocument($content, $mimeType);
        $documentType = $classificationResult['DocumentType'] ?? null;

        if (is_null($documentType)) {
            return [];
        }

        $result = ['DocumentType' => $documentType];

        // Determine which extractor to use based on classification
        $processorIdToUse = null;
        $isCertificate = false;
        $isMoa = false;

        if (Str::contains($documentType, ['CERTIFICATE'], true)) {
            $processorIdToUse = $this->certificateExtractionProcessorId;
            $isCertificate = true;
        } elseif (Str::contains($documentType, ['MOA', 'MEMORANDUM', 'AGREEMENT'], true)) { // Adjust MOA classification key as needed
            $processorIdToUse = $this->moaExtractionProcessorId;
            $isMoa = true;
        }

        // Only proceed to extraction if a valid processor was selected
        if ($processorIdToUse) {
            $extractedEntities = $this->extractEntities($content, $mimeType, $processorIdToUse);

            $result = array_merge($result, $extractedEntities);

            // Set flags for convenience in widgets
            $result['IsCertificate'] = $isCertificate;
            $result['IsMoa'] = $isMoa; // NEW FLAG

        } else {
            $result['IsCertificate'] = false;
            $result['IsMoa'] = false;
        }

        return $result;
    }

    // document classification (no change needed here)
    protected function classifyDocument(string $content, string $mimeType): array
    {
        // ... (classification logic remains the same)
        if (empty($this->classificationProcessorId)) {
            Log::error("Missing DOCAI_CLASSIFIER_ID. Skipping classification.");
            return [];
        }

        $name = "projects/{$this->projectId}/locations/{$this->location}/processors/{$this->classificationProcessorId}";
        $client = $this->getClient();

        try {
            $request = (new ProcessRequest())
                ->setName($name)
                ->setRawDocument(
                    (new RawDocument())->setContent($content)->setMimeType($mimeType)
                );

            $response = $client->processDocument($request);
            $document = $response->getDocument();

            $documentType = null;
            $classificationResult = [];

            foreach ($document->getEntities() as $entity) {
                $documentType = $entity->getType() ?? null;
                break;
            }

            if (!empty($documentType)) {
                $classificationResult['DocumentType'] = Str::upper($documentType);
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

    // document extraction - modified to accept the processor ID to use
    protected function extractEntities(string $content, string $mimeType, string $processorId): array
    {
        if (empty($processorId)) {
            Log::error("Missing required Extractor ID. Skipping extraction.");
            return [];
        }

        $name = "projects/{$this->projectId}/locations/{$this->location}/processors/{$processorId}";
        $client = $this->getClient();

        try {
            $request = (new ProcessRequest())
                ->setName($name)
                ->setRawDocument(
                    (new RawDocument())->setContent($content)->setMimeType($mimeType)
                );

            $response = $client->processDocument($request);
            $document = $response->getDocument();

            $extractedEntities = [];
            foreach ($document->getEntities() as $entity) {
                $key = $this->toPascalCase($entity->getType() ?: 'Unknown');
                $value = $entity->getMentionText() ?: null;
                $extractedEntities[$key] = $value;
            }

            Log::info('Extracted entities', $extractedEntities);
            return $extractedEntities;
        } catch (\Exception $e) {
            Log::error("Document AI Extraction Error: " . $e->getMessage());
            return [];
        } finally {
            $client->close();
        }
    }
}
