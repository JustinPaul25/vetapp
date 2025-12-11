<?php

namespace App\Console\Commands;

use App\Services\AblyService;
use Illuminate\Console\Command;

class TestAbly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ably:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Ably connection and publish a test message';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Ably connection...');

        $apiKey = config('services.ably.key');
        
        if (!$apiKey) {
            $this->error('❌ ABLY_KEY is not set in your .env file');
            return Command::FAILURE;
        }

        $this->info('✓ ABLY_KEY is configured');
        $this->line('API Key: ' . substr($apiKey, 0, 20) . '...');

        try {
            $ablyService = app(AblyService::class);
            
            $testData = [
                'message' => 'Test message from Laravel',
                'timestamp' => now()->toDateTimeString(),
                'test' => true,
            ];

            $this->info('Publishing test message to admin:notifications channel...');
            
            $result = $ablyService->publishToAdmins('test', $testData);
            
            if ($result) {
                $this->info('✅ Success! Ably is working correctly.');
                $this->line('Test message published to channel: admin:notifications');
                $this->line('Event: test');
                $this->line('Data: ' . json_encode($testData, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            } else {
                $this->error('❌ Failed to publish message. Check logs for details.');
                $this->line('Run: php artisan tail or check storage/logs/laravel.log');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->line('Check your ABLY_KEY is valid and has publish permissions.');
            return Command::FAILURE;
        }
    }
}

