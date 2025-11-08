<?php

namespace App\Services;

use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
        $this->credentialsPath = str_starts_with($envPath, base_path())
            ? $envPath
            : base_path($envPath);

        if (file_exists($this->credentialsPath)) {
            putenv("GOOGLE_APPLICATION_CREDENTIALS={$this->credentialsPath}");
        } else {
            Log::warning("Google credentials file missing or invalid.", [
                'path' => $this->credentialsPath
            ]);
        }
    }

    // document AI client
    protected function getClient(): DocumentProcessorServiceClient
    {
        if (!file_exists($this->credentialsPath)) {
            throw new \Exception("Google credentials file not found at: {$this->credentialsPath}");
        }

        return new DocumentProcessorServiceClient([
            'credentials' => $this->credentialsPath,
        ]);
    }

    //document processing
    public function processDocument(TemporaryUploadedFile $uploadedFile): array
    {
        $content = $uploadedFile->get();
        $mimeType = $uploadedFile->getMimeType();

        $classificationLabel = $this->classifyDocument($content, $mimeType);

        if (!in_array(strtoupper($classificationLabel), ['CERTIFICATE', 'DIPLOMA'])) {
            Log::info('Document rejected', ['label' => $classificationLabel]);
            return ['IsCertificate' => false];
        }

        $extractedData = $this->extractEntities($content, $mimeType);
        $extractedData['IsCertificate'] = true;

        return $extractedData;
    }

    //document classification
    protected function classifyDocument(string $content, string $mimeType): ?string
    {
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

            /** @var \Google\Protobuf\Internal\RepeatedField $repeatedEntities */
            $repeatedEntities = $document->getEntities();

            $entities = iterator_to_array($repeatedEntities);

            Log::info('Document AI classification entities:', array_map(
                fn($e) => [
                    'type' => $e->getType(),
                    'mentionText' => $e->getMentionText()
                ],
                $entities
            ));

            $firstType = $entities[0]->getType() ?? null;
            return strtoupper($firstType ?? 'UNKNOWN');
        } catch (\Exception $e) {
            Log::error("Document AI Classification Error: " . $e->getMessage());
            return null;
        } finally {
            $client->close();
        }
    }

    //document extraction
    protected function extractEntities(string $content, string $mimeType): array
    {
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

            /** @var \Google\Protobuf\Internal\RepeatedField $repeatedEntities */
            $repeatedEntities = $document->getEntities();

            $entities = iterator_to_array($repeatedEntities);

            $extractedEntities = [];
            foreach ($entities as $entity) {
                $key = $entity->getType() ?: 'Unknown';
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
