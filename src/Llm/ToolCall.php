<?php

namespace App\Llm;

final readonly class ToolCall
{
    /** @param array<string, mixed> $arguments */
    public function __construct(
        public string $name,
        public array $arguments,
    ) {
    }
}
