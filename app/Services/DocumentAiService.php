<?php

namespace App\Services;

use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Str; // Import the Str facade for case conversion

class DocumentAiService
{
    protected string $classificationProcessorId;
    protected string $extractionProcessorId;
    protected string $location;
    protected string $projectId;
    protected string $credentialsPath;

    public function __construct()
    {
        $this->projectId = config('services.google.project_id', env('GOOGLE_CLOUD_PROJECT_ID', ''));
        $this->location = config('services.google.location', env('DOCAI_LOCATION', 'us'));
        $this->classificationProcessorId = env('DOCAI_CLASSIFIER_ID', '');
        $this->extractionProcessorId = env('DOCAI_EXTRACTOR_ID', '');

        //project ID validation
        if (empty($this->projectId)) {
            Log::error("Missing GOOGLE_CLOUD_PROJECT_ID — check .env or config/services.php");
            throw new \RuntimeException("Missing GOOGLE_CLOUD_PROJECT_ID in environment.");
        }

        // resolving credentials path
        $envPath = env('GOOGLE_APPLICATION_CREDENTIALS', 'google-service-account.json');

        // FIX START: Check for absolute path (Unix starts with /, Windows has drive letter/colon)
        $isAbsolute = Str::startsWith($envPath, ['/', '\\']) || (strlen($envPath) > 1 && $envPath[1] === ':');

        if ($isAbsolute) {
            // It is an absolute path (like C:\...), use it directly.
            $this->credentialsPath = $envPath;
        } else {
            // It is a relative path (like google-service-account.json), prepend base_path().
            $this->credentialsPath = base_path($envPath);
        }
        // FIX END
    }

    protected function getClient(): DocumentProcessorServiceClient
    {
        return new DocumentProcessorServiceClient([
            'credentials' => $this->credentialsPath
        ]);
    }

    // NEW HELPER: Standardize entity keys to PascalCase
    protected function toPascalCase(string $string): string
    {
        // Convert to snake_case first to handle existing casings (camelCase, Title Case, etc.)
        $snakeCase = Str::snake($string);
        // Then convert to PascalCase (Title Case, removing spaces/underscores)
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $snakeCase)));
    }

    public function processDocument(TemporaryUploadedFile $file): array
    {
        $content = $file->get();
        $mimeType = $file->getMimeType();

        // Step 1: Classification
        $classificationResult = $this->classifyDocument($content, $mimeType);
        $documentType = $classificationResult['DocumentType'] ?? null;

        // Return immediately if classification fails
        if (is_null($documentType)) {
            return [];
        }

        $result = ['DocumentType' => $documentType];

        // Only proceed to extraction if it's a known certificate type that requires extraction
        if (Str::contains($documentType, ['CERTIFICATE'], true)) {
            $extractedEntities = $this->extractEntities($content, $mimeType);

            // Merge extracted entities.
            $result = array_merge($result, $extractedEntities);

            // Set IsCertificate for convenience in widgets
            $result['IsCertificate'] = true;
        } else {
            // Set IsCertificate to false for convenience in widgets
            $result['IsCertificate'] = false;
        }

        return $result;
    }

    // document classification
    protected function classifyDocument(string $content, string $mimeType): array
    {
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

            // FIX: Iterate directly over the RepeatedField object
            $documentType = null;
            $classificationResult = [];

            foreach ($document->getEntities() as $entity) {
                // Assume the first entity's type is the classified document type.
                $documentType = $entity->getType() ?? null;
                break; // We only need the first entity for classification type
            }

            if (!empty($documentType)) {
                // Ensure the document type is always uppercase for reliable comparison (e.g., 'CERTIFICATE')
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

    //document extraction
    protected function extractEntities(string $content, string $mimeType): array
    {
        if (empty($this->extractionProcessorId)) {
            Log::error("Missing DOCAI_EXTRACTOR_ID. Skipping extraction.");
            return [];
        }

        $name = "projects/{$this->projectId}/locations/{$this->location}/processors/{$this->extractionProcessorId}";
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
            // FIX: Iterate directly over the RepeatedField object
            foreach ($document->getEntities() as $entity) {
                // APPLYING THE CASE CONVERSION HERE
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
