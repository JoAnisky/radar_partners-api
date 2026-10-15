<?php

namespace App\Llm;

use App\Llm\Tool\ChatToolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Boucle de conversation : le modèle appelle des outils jusqu'à produire une réponse. */
final readonly class ChatService
{
    private const MAX_ROUNDS = 5;

    /** @param iterable<ChatToolInterface> $tools */
    public function __construct(
        private LlmClientInterface $client,
        #[AutowireIterator('app.chat_tool')]
        private iterable $tools,
        #[Autowire('%kernel.project_dir%/config/assistant')]
        private string $promptDir,
    ) {
    }

    /** @param list<LlmMessage> $history messages `user` et `assistant` de la conversation */
    public function answer(array $history, \DateTimeImmutable $now): ChatResult
    {
        $tools = [];
        foreach ($this->tools as $tool) {
            $tools[$tool->getName()] = $tool;
        }

        $messages = [new LlmMessage(LlmMessage::SYSTEM, $this->systemPrompt($now)), ...$history];
        $calls = [];

        for ($round = 0; $round < self::MAX_ROUNDS; ++$round) {
            $response = $this->client->chat($messages, array_values($tools));
            if ([] === $response->toolCalls) {
                return new ChatResult(trim($response->content), $calls);
            }

            $messages[] = $response;
            foreach ($response->toolCalls as $call) {
                $calls[] = $call;
                $messages[] = new LlmMessage(LlmMessage::TOOL, $this->run($tools[$call->name] ?? null, $call), toolName: $call->name);
            }
        }

        return new ChatResult("Je n'ai pas réussi à répondre en un nombre raisonnable d'étapes. Reformule ou précise ta question.", $calls);
    }

    private function run(?ChatToolInterface $tool, ToolCall $call): string
    {
        if (null === $tool) {
            return (string) json_encode(['error' => sprintf('Outil inconnu : %s.', $call->name)]);
        }

        try {
            return (string) json_encode($tool->execute($call->arguments), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            return (string) json_encode(['error' => $e->getMessage()], \JSON_UNESCAPED_UNICODE);
        }
    }

    private function systemPrompt(\DateTimeImmutable $now): string
    {
        $prompt = (string) file_get_contents($this->promptDir.'/system.md');
        $profile = is_file($this->promptDir.'/profile.md') ? trim((string) file_get_contents($this->promptDir.'/profile.md')) : '';

        return strtr($prompt, [
            '{date}' => $now->format('Y-m-d'),
            '{profile}' => '' === $profile ? '(profil non renseigné)' : $profile,
        ]);
    }
}
