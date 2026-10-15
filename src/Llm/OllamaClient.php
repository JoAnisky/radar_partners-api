<?php

namespace App\Llm;

use App\Llm\Tool\ChatToolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OllamaClient implements LlmClientInterface
{
    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%env(OLLAMA_URL)%')]
        private string $url,
        #[Autowire('%env(OLLAMA_MODEL)%')]
        private string $model,
        #[Autowire('%env(int:OLLAMA_NUM_CTX)%')]
        private int $numCtx,
    ) {
    }

    public function chat(array $messages, array $tools): LlmMessage
    {
        try {
            /** @var array{message?: array{content?: string, tool_calls?: list<array{function: array{name: string, arguments?: array<string, mixed>}}>}} $data */
            $data = $this->http->request('POST', rtrim($this->url, '/').'/api/chat', [
                'timeout' => 300,
                'json' => [
                    'model' => $this->model,
                    'stream' => false,
                    'options' => ['num_ctx' => $this->numCtx],
                    'messages' => array_map(self::serializeMessage(...), $messages),
                    'tools' => array_map(self::serializeTool(...), $tools),
                ],
            ])->toArray();
        } catch (ExceptionInterface $e) {
            throw new LlmUnavailableException('Le modèle local ne répond pas : '.$e->getMessage(), 0, $e);
        }

        $message = $data['message'] ?? [];

        return new LlmMessage(
            LlmMessage::ASSISTANT,
            $message['content'] ?? '',
            array_map(
                static fn (array $call): ToolCall => new ToolCall($call['function']['name'], $call['function']['arguments'] ?? []),
                $message['tool_calls'] ?? [],
            ),
        );
    }

    /** @return array<string, mixed> */
    private static function serializeMessage(LlmMessage $message): array
    {
        $data = ['role' => $message->role, 'content' => $message->content];
        if ([] !== $message->toolCalls) {
            $data['tool_calls'] = array_map(
                static fn (ToolCall $call): array => ['function' => ['name' => $call->name, 'arguments' => (object) $call->arguments]],
                $message->toolCalls,
            );
        }
        if (null !== $message->toolName) {
            $data['tool_name'] = $message->toolName;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private static function serializeTool(ChatToolInterface $tool): array
    {
        return ['type' => 'function', 'function' => [
            'name' => $tool->getName(),
            'description' => $tool->getDescription(),
            'parameters' => $tool->getParameters(),
        ]];
    }
}
