<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaCloudService
{
    /**
     * Send a chat conversation to Ollama Cloud and return the assistant's reply text.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(array $messages): string
    {
        $url = config('services.ollama_cloud.url');
        $apiKey = config('services.ollama_cloud.api_key');
        $model = config('services.ollama_cloud.model');

        if (empty($apiKey)) {
            return "The AI Assistant is not configured yet. Please set OLLAMA_CLOUD_API_KEY in your .env file. You can generate a key at ollama.com after signing up for Ollama Cloud.";
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post($url, [
                    'model' => $model,
                    'messages' => $messages,
                    'stream' => false,
                ]);

            if ($response->failed()) {
                Log::warning('Ollama Cloud request failed', ['status' => $response->status(), 'body' => $response->body()]);

                return 'Sorry, the AI Assistant could not reach Ollama Cloud right now (HTTP ' . $response->status() . '). Please try again in a moment.';
            }

            $json = $response->json();

            return $json['message']['content']
                ?? $json['messages'][0]['content']
                ?? 'The assistant did not return a response. Please try again.';
        } catch (\Throwable $e) {
            Log::error('Ollama Cloud exception', ['message' => $e->getMessage()]);

            return 'Sorry, something went wrong while talking to the AI Assistant: ' . $e->getMessage();
        }
    }
}
