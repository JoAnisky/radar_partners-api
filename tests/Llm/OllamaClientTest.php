<?php

namespace App\Tests\Llm;

use App\Llm\LlmMessage;
use App\Llm\LlmUnavailableException;
use App\Llm\OllamaClient;
use App\Llm\ToolCall;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OllamaClientTest extends TestCase
{
    public function testSendsConversationAndParsesToolCalls(): void
    {
        $method = $url = $raw = '';
        $http = new MockHttpClient(function (string $m, string $u, array $options) use (&$method, &$url, &$raw) {
            $method = $m;
            $url = $u;
            $raw = \is_string($options['body']) ? $options['body'] : '';

            return new JsonMockResponse(['message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [
                ['function' => ['name' => 'search_companies', 'arguments' => ['due' => true]]],
            ]]]);
        });

        $response = (new OllamaClient($http, 'http://llm:11434/', 'test-model', 4096))->chat([
            new LlmMessage(LlmMessage::USER, 'Qui relancer ?'),
            new LlmMessage(LlmMessage::ASSISTANT, '', [new ToolCall('search_companies', [])]),
            new LlmMessage(LlmMessage::TOOL, '{}', toolName: 'search_companies'),
        ], []);

        /** @var array{model: string, stream: bool, options: array{num_ctx: int}, messages: list<array{tool_name?: string, tool_calls?: list<array{function: array{arguments: array<string, mixed>}}>}>} $body */
        $body = json_decode($raw, true);

        self::assertSame('POST', $method);
        self::assertSame('http://llm:11434/api/chat', $url);
        self::assertSame('test-model', $body['model']);
        self::assertFalse($body['stream']);
        self::assertSame(4096, $body['options']['num_ctx']);
        self::assertSame('search_companies', $body['messages'][2]['tool_name'] ?? null);
        self::assertSame([], $body['messages'][1]['tool_calls'][0]['function']['arguments'] ?? null);
        self::assertEquals([new ToolCall('search_companies', ['due' => true])], $response->toolCalls);
    }

    public function testReadsPlainAnswer(): void
    {
        $http = new MockHttpClient(new JsonMockResponse(['message' => ['content' => 'Bonjour']]));

        $response = (new OllamaClient($http, 'http://llm', 'm', 4096))->chat([new LlmMessage(LlmMessage::USER, 'Salut')], []);

        self::assertSame('Bonjour', $response->content);
        self::assertSame([], $response->toolCalls);
    }

    public function testServerErrorBecomesUnavailable(): void
    {
        $http = new MockHttpClient(new MockResponse('nope', ['http_code' => 500]));

        $this->expectException(LlmUnavailableException::class);

        (new OllamaClient($http, 'http://llm', 'm', 4096))->chat([new LlmMessage(LlmMessage::USER, 'Salut')], []);
    }
}
