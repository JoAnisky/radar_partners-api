<?php

namespace App\Command;

use App\Llm\ChatService;
use App\Llm\LlmMessage;
use App\Llm\OllamaClient;
use App\Llm\Tool\ChatToolInterface;
use App\Repository\CompanyRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'app:llm:bench', description: 'Pose les mêmes questions à plusieurs modèles Ollama et compare outils, réponses et durées')]
final readonly class BenchLlmCommand
{
    /** @param iterable<ChatToolInterface> $tools */
    public function __construct(
        private HttpClientInterface $http,
        private CompanyRepository $companies,
        #[AutowireIterator('app.chat_tool')]
        private iterable $tools,
        #[Autowire('%kernel.project_dir%/config/assistant')]
        private string $promptDir,
        #[Autowire('%env(OLLAMA_URL)%')]
        private string $url,
        #[Autowire('%env(int:OLLAMA_NUM_CTX)%')]
        private int $numCtx,
    ) {
    }

    /** @param list<string> $models */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Modèles à comparer (déjà téléchargés), ex. granite4.1:8b gemma4:12b')] array $models,
        #[Option(description: 'Entreprise utilisée pour le résumé (id) ; par défaut la mieux classée')] ?int $company = null,
    ): int {
        $target = null === $company ? $this->companies->findOneBy(['archivedAt' => null], ['score' => 'DESC']) : $this->companies->find($company);
        if (null === $target) {
            $io->error('Aucune entreprise trouvée pour la question de résumé.');

            return 1;
        }

        $questions = [
            'Recherche' => 'Quelles agences web ou ESN as-tu en base ? Donne les plus intéressantes.',
            'Signaux récents' => 'Quels sont les derniers signaux à traiter ?',
            'Résumé' => sprintf('Fais-moi un résumé de la fiche « %s » (id %d).', $target->getName(), $target->getId()),
            'Piège' => 'Quelle est l\'adresse e-mail du directeur technique de la société Zorglub Industries ?',
        ];

        $now = new \DateTimeImmutable();
        foreach ($models as $model) {
            $io->section($model);
            $service = new ChatService(new OllamaClient($this->http, $this->url, $model, $this->numCtx), $this->tools, $this->promptDir);

            $io->writeln('Chargement du modèle…');
            $service->answer([new LlmMessage(LlmMessage::USER, 'Bonjour')], $now);

            foreach ($questions as $label => $question) {
                $start = hrtime(true);
                try {
                    $result = $service->answer([new LlmMessage(LlmMessage::USER, $question)], $now);
                } catch (\Throwable $e) {
                    $io->error(sprintf('%s : %s', $label, $e->getMessage()));
                    continue;
                }
                $seconds = (hrtime(true) - $start) / 1e9;

                $io->title(sprintf('%s — %.1f s', $label, $seconds));
                $io->writeln('<comment>Question</comment> : '.$question);
                $io->writeln('<comment>Outils</comment>   : '.([] === $result->toolCalls ? '(aucun)' : implode(', ', array_map(
                    static fn ($call): string => $call->name.json_encode($call->arguments, \JSON_UNESCAPED_UNICODE),
                    $result->toolCalls,
                ))));
                $io->writeln('<comment>Réponse</comment> :');
                $io->writeln($result->reply);
                $io->newLine();
            }
        }

        return 0;
    }
}
