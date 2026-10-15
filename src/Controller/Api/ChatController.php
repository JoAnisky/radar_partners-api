<?php

namespace App\Controller\Api;

use App\Llm\ChatService;
use App\Llm\LlmMessage;
use App\Llm\LlmUnavailableException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/chat', methods: ['POST'])]
final class ChatController extends AbstractController
{
    private const MAX_MESSAGES = 30;

    public function __construct(private readonly ChatService $chat)
    {
    }

    /** Corps : `{"messages": [{"role": "user"|"assistant", "content": "..."}]}`, le dernier message étant de l'utilisateur. */
    public function __invoke(Request $request): JsonResponse
    {
        $history = $this->history($request->toArray()['messages'] ?? null);

        try {
            $result = $this->chat->answer($history, new \DateTimeImmutable());
        } catch (LlmUnavailableException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json([
            'reply' => $result->reply,
            'toolCalls' => array_map(static fn ($call): array => ['name' => $call->name, 'arguments' => $call->arguments], $result->toolCalls),
        ]);
    }

    /** @return list<LlmMessage> */
    private function history(mixed $messages): array
    {
        if (!\is_array($messages) || [] === $messages || \count($messages) > self::MAX_MESSAGES) {
            throw new UnprocessableEntityHttpException(sprintf('messages doit contenir de 1 à %d éléments.', self::MAX_MESSAGES));
        }

        $history = [];
        foreach (array_values($messages) as $message) {
            $role = \is_array($message) ? ($message['role'] ?? null) : null;
            $content = \is_array($message) ? ($message['content'] ?? null) : null;
            if (!\in_array($role, [LlmMessage::USER, LlmMessage::ASSISTANT], true) || !\is_string($content) || '' === trim($content)) {
                throw new UnprocessableEntityHttpException('Chaque message doit avoir un role (user ou assistant) et un content non vide.');
            }
            $history[] = new LlmMessage($role, $content);
        }
        if (LlmMessage::USER !== $history[\count($history) - 1]->role) {
            throw new UnprocessableEntityHttpException('Le dernier message doit venir de l\'utilisateur.');
        }

        return $history;
    }
}
