<?php

namespace App\Llm;

use App\Llm\Demo\DemoLlmClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class LlmClientFactory
{
    public function __construct(
        private OllamaClient $ollama,
        private DemoLlmClient $demo,
        #[Autowire('%app.demo_mode%')]
        private bool $demoMode,
    ) {
    }

    public function create(): LlmClientInterface
    {
        return $this->demoMode ? $this->demo : $this->ollama;
    }
}
