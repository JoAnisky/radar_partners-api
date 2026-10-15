<?php

namespace App\Llm;

use App\Llm\Tool\ChatToolInterface;

interface LlmClientInterface
{
    /**
     * @param list<LlmMessage>        $messages
     * @param list<ChatToolInterface> $tools
     *
     * @throws LlmUnavailableException
     */
    public function chat(array $messages, array $tools): LlmMessage;
}
