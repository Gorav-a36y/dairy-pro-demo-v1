<?php

return [
    'ollama_cloud' => [
        'url' => env('OLLAMA_CLOUD_URL', 'https://ollama.com/api/chat'),
        'api_key' => env('OLLAMA_CLOUD_API_KEY'),
        'model' => env('OLLAMA_CLOUD_MODEL', 'llama3.1'),
    ],
];
