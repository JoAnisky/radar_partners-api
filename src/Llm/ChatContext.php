<?php

namespace App\Llm;

/** Ce que l'utilisateur est en train de consulter dans l'outil. */
final readonly class ChatContext
{
    public function __construct(
        public ?int $companyId = null,
        public ?string $companyName = null,
        public ?string $page = null,
    ) {
    }

    public function describe(): string
    {
        $lines = [];
        if (null !== $this->page) {
            $lines[] = sprintf('Page ouverte : %s.', $this->page);
        }
        if (null !== $this->companyId) {
            $lines[] = sprintf(
                "L'utilisateur consulte la fiche de l'entreprise « %s » (id %d). Quand il dit « cette entreprise », « elle » ou « ici », il parle de celle-ci : appelle `get_company` avec id %d avant de répondre ou de rédiger.",
                $this->companyName ?? '?',
                $this->companyId,
                $this->companyId,
            );
        }

        return [] === $lines ? '(aucun)' : implode("\n", $lines);
    }
}
