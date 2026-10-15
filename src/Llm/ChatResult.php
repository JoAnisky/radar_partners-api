<?php

namespace App\Llm;

final readonly class ChatResult
{
    /** @param list<ToolCall> $toolCalls outils appelés pendant la réponse, dans l'ordre */
    public function __construct(
        public string $reply,
        public array $toolCalls,
    ) {
    }
}
