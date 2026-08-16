<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaCloudService
{
    public function chat(array $messages): string
    {
        $setting = Setting::current();

        $url    = config('services.ollama_cloud.url');
        $apiKey = $setting->ollama_api_key;
        $model  = config('services.ollama_cloud.model');

        // DEBUG: Remove this after fixing
        Log::debug('Ollama API Key check', [
            'has_key' => !empty($apiKey),
            'key_length' => $apiKey ? strlen($apiKey) : 0,
        ]);

        if (empty($apiKey)) {
            return "The AI Assistant is not configured yet. Please add your Ollama Cloud API key in Settings → AI Assistant.";
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post($url, [
                    'model'    => $model,
                    'messages' => $messages,
                    'stream'   => false,
                ]);

            if ($response->failed()) {
                Log::warning('Ollama Cloud request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                if ($response->status() === 401) {
                    return "Your Ollama Cloud API key appears to be invalid (HTTP 401). Please check it in Settings → AI Assistant.";
                }

                if ($response->status() === 404) {
                    return "Sorry, Ollama Cloud couldn't find the model \"{$model}\" (HTTP 404). Cloud models need a \":cloud\" tag.";
                }

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