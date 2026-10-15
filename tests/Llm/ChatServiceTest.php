<?php

namespace App\Tests\Llm;

use App\Llm\ChatService;
use App\Llm\LlmClientInterface;
use App\Llm\LlmMessage;
use App\Llm\Tool\ChatToolInterface;
use App\Llm\ToolCall;
use PHPUnit\Framework\TestCase;

final class ChatServiceTest extends TestCase
{
    private const PROMPT_DIR = __DIR__.'/../../config/assistant';

    private static function echoTool(bool $fails = false): ChatToolInterface
    {
        return new class($fails) implements ChatToolInterface {
            public function __construct(private readonly bool $fails)
            {
            }

            public function getName(): string
            {
                return 'echo';
            }

            public function getDescription(): string
            {
                return 'Renvoie ses arguments.';
            }

            public function getParameters(): array
            {
                return ['type' => 'object', 'properties' => []];
            }

            public function execute(array $arguments): array
            {
                return $this->fails ? throw new \RuntimeException('boom') : ['got' => $arguments];
            }
        };
    }

    /** @param list<LlmMessage> $responses */
    private static function client(array $responses): RecordingClient
    {
        return new RecordingClient($responses);
    }

    private static function service(LlmClientInterface $client, bool $toolFails = false): ChatService
    {
        return new ChatService($client, [self::echoTool($toolFails)], self::PROMPT_DIR);
    }

    public function testAnswersWithoutTool(): void
    {
        $client = self::client([new LlmMessage(LlmMessage::ASSISTANT, ' Bonjour ')]);
        $service = self::service($client);

        $result = $service->answer([new LlmMessage(LlmMessage::USER, 'Salut')], new \DateTimeImmutable('2026-10-15'));

        self::assertSame('Bonjour', $result->reply);
        self::assertSame([], $result->toolCalls);
        $system = $client->sent[0][0];
        self::assertSame(LlmMessage::SYSTEM, $system->role);
        self::assertStringContainsString('2026-10-15', $system->content);
        self::assertStringNotContainsString('{date}', $system->content);
    }

    public function testRunsToolsThenAnswers(): void
    {
        $call = new ToolCall('echo', ['x' => 1]);
        $client = self::client([
            new LlmMessage(LlmMessage::ASSISTANT, '', [$call]),
            new LlmMessage(LlmMessage::ASSISTANT, 'Voilà'),
        ]);
        $service = self::service($client);

        $result = $service->answer([new LlmMessage(LlmMessage::USER, 'Fais')], new \DateTimeImmutable());

        self::assertSame('Voilà', $result->reply);
        self::assertEquals([$call], $result->toolCalls);
        $second = $client->sent[1];
        $toolMessage = $second[\count($second) - 1];
        self::assertSame(LlmMessage::TOOL, $toolMessage->role);
        self::assertSame('echo', $toolMessage->toolName);
        self::assertSame('{"got":{"x":1}}', $toolMessage->content);
    }

    public function testToolErrorsAreReturnedToTheModel(): void
    {
        $client = self::client([
            new LlmMessage(LlmMessage::ASSISTANT, '', [new ToolCall('echo', []), new ToolCall('missing', [])]),
            new LlmMessage(LlmMessage::ASSISTANT, 'Désolé'),
        ]);
        $service = self::service($client, toolFails: true);

        $service->answer([new LlmMessage(LlmMessage::USER, 'Fais')], new \DateTimeImmutable());

        $contents = array_map(static fn (LlmMessage $m): string => $m->content, \array_slice($client->sent[1], -2));
        self::assertSame(['{"error":"boom"}', '{"error":"Outil inconnu : missing."}'], $contents);
    }

    public function testGivesUpAfterTooManyRounds(): void
    {
        $loop = new LlmMessage(LlmMessage::ASSISTANT, '', [new ToolCall('echo', [])]);
        $service = self::service(self::client(array_fill(0, 10, $loop)));

        $result = $service->answer([new LlmMessage(LlmMessage::USER, 'Fais')], new \DateTimeImmutable());

        self::assertStringContainsString('pas réussi', $result->reply);
        self::assertCount(5, $result->toolCalls);
    }
}

final class RecordingClient implements LlmClientInterface
{
    /** @var list<list<LlmMessage>> */
    public array $sent = [];

    /** @param list<LlmMessage> $responses */
    public function __construct(private array $responses)
    {
    }

    public function chat(array $messages, array $tools): LlmMessage
    {
        $this->sent[] = $messages;

        return array_shift($this->responses) ?? new LlmMessage(LlmMessage::ASSISTANT, '');
    }
}
