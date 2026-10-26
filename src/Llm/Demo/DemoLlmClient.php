<?php

namespace App\Llm\Demo;

use App\Llm\LlmClientInterface;
use App\Llm\LlmMessage;
use App\Llm\ToolCall;
use App\Repository\CompanyRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Faux modèle de la démo : reconnaît cinq questions, appelle les vrais outils de lecture et met en forme leur résultat. */
final readonly class DemoLlmClient implements LlmClientInterface
{
    public function __construct(
        private CompanyRepository $companies,
        #[Autowire('%app.demo_chat_delay_ms%')]
        private int $delayMs,
    ) {
    }

    public function chat(array $messages, array $tools): LlmMessage
    {
        $lastUser = array_key_last(array_filter($messages, static fn (LlmMessage $m): bool => LlmMessage::USER === $m->role));
        $question = null === $lastUser ? '' : $messages[$lastUser]->content;
        $normalized = self::normalize($question);
        $intent = DemoIntent::detect($normalized);

        if (null === $intent) {
            return $this->reply(DemoReplies::help());
        }

        $results = array_values(array_filter(\array_slice($messages, (int) $lastUser), static fn (LlmMessage $m): bool => LlmMessage::TOOL === $m->role));
        if ([] !== $results) {
            /** @var array<string, mixed> $data */
            $data = json_decode($results[0]->content, true) ?: [];

            return $this->reply(DemoReplies::answer($intent, $data, $normalized));
        }

        if ($intent->needsCompany()) {
            $id = $this->companyId($normalized, $messages);

            return null === $id ? $this->reply(DemoReplies::askCompany()) : self::call('get_company', ['id' => $id]);
        }

        return match ($intent) {
            DemoIntent::Priorities => self::call('search_companies', ['status' => 'TO_CONTACT', 'limit' => 5]),
            DemoIntent::Overdue => self::call('search_companies', ['due' => true, 'limit' => 8]),
            default => self::call('list_signals', ['limit' => 5]),
        };
    }

    /** @param list<LlmMessage> $messages */
    private function companyId(string $question, array $messages): ?int
    {
        if (1 === preg_match('/\bid (\d+)\b/', $question, $m)) {
            return (int) $m[1];
        }

        $best = null;
        foreach ($this->companies->findBy(['archivedAt' => null]) as $company) {
            $name = self::normalize($company->getName());
            if (str_contains($question, $name) && (null === $best || \strlen($name) > $best[0])) {
                $best = [\strlen($name), $company->getId()];
            }
        }
        if (null !== $best) {
            return $best[1];
        }

        // Fiche ouverte : le contexte est injecté dans l'invite système.
        return 1 === preg_match('/\(id (\d+)\)/', $messages[0]->content ?? '', $m) ? (int) $m[1] : null;
    }

    /** @param array<string, mixed> $arguments */
    private static function call(string $tool, array $arguments): LlmMessage
    {
        return new LlmMessage(LlmMessage::ASSISTANT, '', [new ToolCall($tool, $arguments)]);
    }

    /** Réponse finale, précédée d'un court temps de réflexion pour faire plus naturel. */
    private function reply(string $content): LlmMessage
    {
        usleep($this->delayMs * 1000);

        return new LlmMessage(LlmMessage::ASSISTANT, $content);
    }

    public static function normalize(string $text): string
    {
        return strtr(mb_strtolower($text), ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', '’' => "'"]);
    }
}
