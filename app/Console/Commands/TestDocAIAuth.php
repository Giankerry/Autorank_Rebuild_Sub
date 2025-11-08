<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient; 
use Exception;

class TestDocAIAuth extends Command
{
    protected $signature = 'test:docai-auth';
    protected $description = 'Tests Google Document AI authentication and client initialization.';

    public function handle()
    {
        $this->info('Attempting to initialize Google Document AI client...');
        
        try {
            // Correct class from the Google Cloud Document AI v2.5 SDK:
            $client = new DocumentProcessorServiceClient();
            $client->close();
            
            $this->info('✅ Google Document AI Client initialized successfully. Authentication appears valid.');

        } catch (Exception $e) {
            $this->error('❌ Google Document AI Auth Failed.');
            $this->comment('Error Message: ' . $e->getMessage());
            $this->comment('Ensure GOOGLE_APPLICATION_CREDENTIALS is set and the JSON file path is correct.');
        }

        return Command::SUCCESS;
    }
}
