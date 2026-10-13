<?php

namespace App\Signal;

use App\Entity\Signal;
use App\Repository\SignalRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SignalIngester
{
    public function __construct(
        private EntityManagerInterface $em,
        private SignalRepository $signals,
        private StackFilter $stackFilter,
    ) {
    }

    public function ingest(RawSignal $raw): IngestOutcome
    {
        if (!$this->stackFilter->matches($raw)) {
            return IngestOutcome::Filtered;
        }

        if (null !== $this->signals->findOneBy(['fingerprint' => $raw->fingerprint])) {
            return IngestOutcome::Duplicate;
        }

        $signal = (new Signal($raw->type, $raw->source, $raw->fingerprint))
            ->setUrl($raw->url)
            ->setTitle($raw->title)
            ->setRawContent($raw->rawContent)
            ->setExtracted(null === $raw->companyName ? $raw->extracted : $raw->extracted + ['companyName' => $raw->companyName])
            ->setPublishedAt($raw->publishedAt);

        if (null !== $raw->observedAt) {
            $signal->setDetectedAt($raw->observedAt);
        }

        $this->em->persist($signal);

        return IngestOutcome::Created;
    }
}
