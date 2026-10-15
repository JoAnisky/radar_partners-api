<?php

namespace App\Llm;

final readonly class LlmMessage
{
    public const SYSTEM = 'system';
    public const USER = 'user';
    public const ASSISTANT = 'assistant';
    public const TOOL = 'tool';

    /** @param list<ToolCall> $toolCalls */
    public function __construct(
        public string $role,
        public string $content,
        public array $toolCalls = [],
        public ?string $toolName = null,
    ) {
    }
}
