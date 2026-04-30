<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestAIInsights extends Command
{
    protected $signature = 'ai:test {--key= : Groq API key to test}';
    protected $description = 'Test AI insights functionality';

    public function handle()
    {
        $apiKey = $this->option('key') ?: env('GROQ_API_KEY');
        
        if (!$apiKey) {
            $this->error('No API key provided. Use --key option or set GROQ_API_KEY in .env');
            return 1;
        }

        $this->info('Testing Groq AI connection...');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.1-8b-instant',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Ты - эксперт по бизнес-аналитике. Отвечай кратко на русском языке.'
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Проанализируй: 100 клиентов, 50 активных. Дай краткий анализ и 2 рекомендации.'
                    ]
                ],
                'max_tokens' => 500,
                'temperature' => 0.7
            ]);

            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'];
                $this->info('✅ AI connection successful!');
                $this->line('');
                $this->line('Sample response:');
                $this->line($content);
                return 0;
            } else {
                $this->error('❌ AI API Error: ' . $response->body());
                return 1;
            }

        } catch (\Exception $e) {
            $this->error('❌ Connection failed: ' . $e->getMessage());
            return 1;
        }
    }
}
