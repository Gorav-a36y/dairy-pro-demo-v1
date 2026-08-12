<?php

namespace App\Http\Controllers;

use App\Services\OllamaCloudService;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    public function index(Request $request)
    {
        $history = $request->session()->get('ai_chat_history', []);

        return view('ai.index', compact('history'));
    }

    public function send(Request $request, OllamaCloudService $ollama)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $history = $request->session()->get('ai_chat_history', []);

        $systemPrompt = [
            'role' => 'system',
            'content' => 'You are the ' . (\App\Models\Setting::current()->dairy_name ?? 'DairyPro') . ' AI Assistant, built by GoravAI. You help the owner of a small dairy '
                . 'business (milk, yogurt, ghee, butter, cheese) with questions about production planning, milk collection from '
                . 'suppliers, ingredient costing, batch sizing, khata (customer/supplier credit ledgers), and sales trends. Keep '
                . 'answers short, practical, and easy to understand. Use plain language.',
        ];

        $history[] = ['role' => 'user', 'content' => $request->message];

        $reply = $ollama->chat(array_merge([$systemPrompt], $history));

        $history[] = ['role' => 'assistant', 'content' => $reply];

        // Keep only the last 20 messages to avoid unbounded session growth
        $history = array_slice($history, -20);

        $request->session()->put('ai_chat_history', $history);

        return back();
    }

    public function clear(Request $request)
    {
        $request->session()->forget('ai_chat_history');

        return back();
    }
}
