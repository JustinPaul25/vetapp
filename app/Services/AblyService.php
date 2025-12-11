<?php

namespace App\Services;

use Ably\AblyRest;
use Illuminate\Support\Facades\Log;

class AblyService
{
    protected $ably;

    public function __construct()
    {
        $apiKey = config('services.ably.key');
        
        if (!$apiKey) {
            Log::warning('Ably API key not configured');
            return;
        }

        $this->ably = new AblyRest($apiKey);
    }

    /**
     * Publish a message to an Ably channel
     *
     * @param string $channelName
     * @param string $eventName
     * @param array $data
     * @return bool
     */
    public function publish(string $channelName, string $eventName, array $data): bool
    {
        if (!$this->ably) {
            return false;
        }

        try {
            $channel = $this->ably->channels->get($channelName);
            $channel->publish($eventName, $data);
            return true;
        } catch (\Exception $e) {
            Log::error('Ably publish failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Publish notification to user-specific channel
     *
     * @param int $userId
     * @param string $eventName
     * @param array $data
     * @return bool
     */
    public function publishToUser(int $userId, string $eventName, array $data): bool
    {
        return $this->publish("user:{$userId}", $eventName, $data);
    }

    /**
     * Publish notification to admin channel
     *
     * @param string $eventName
     * @param array $data
     * @return bool
     */
    public function publishToAdmins(string $eventName, array $data): bool
    {
        return $this->publish('admin:notifications', $eventName, $data);
    }

    /**
     * Publish notification to staff channel
     *
     * @param string $eventName
     * @param array $data
     * @return bool
     */
    public function publishToStaff(string $eventName, array $data): bool
    {
        return $this->publish('staff:notifications', $eventName, $data);
    }

    /**
     * Check if Ably is properly configured
     *
     * @return array
     */
    public function checkStatus(): array
    {
        $apiKey = config('services.ably.key');
        
        $status = [
            'configured' => !empty($apiKey),
            'api_key_set' => !empty($apiKey),
            'ably_instance' => $this->ably !== null,
        ];

        if ($apiKey) {
            $status['api_key_preview'] = substr($apiKey, 0, 20) . '...';
        }

        return $status;
    }
}

