<?php

namespace App\Llm\Tool;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/** Outil en lecture seule que le modèle peut appeler. */
#[AutoconfigureTag('app.chat_tool')]
interface ChatToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /** @return array<string, mixed> schéma JSON des paramètres */
    public function getParameters(): array;

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function execute(array $arguments): array;
}
