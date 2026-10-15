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
        $stack = $this->stackFilter->classify($raw);
        if (StackMatch::Excluded === $stack) {
            return IngestOutcome::Filtered;
        }

        if (null !== $this->signals->findOneBy(['fingerprint' => $raw->fingerprint])) {
            return IngestOutcome::Duplicate;
        }

        $extracted = $raw->extracted;
        if (null !== $raw->companyName) {
            $extracted += ['companyName' => $raw->companyName];
        }
        if (StackMatch::Review === $stack) {
            $extracted['toReview'] = true;
        }
        if (!\array_key_exists('remote', $extracted) && RemoteDetector::isRemote($raw->title.' '.$raw->rawContent)) {
            $extracted['remote'] = true;
        }

        $signal = (new Signal($raw->type, $raw->source, $raw->fingerprint))
            ->setUrl($raw->url)
            ->setTitle($raw->title)
            ->setRawContent($raw->rawContent)
            ->setExtracted($extracted)
            ->setPublishedAt($raw->publishedAt);

        if (null !== $raw->observedAt) {
            $signal->setDetectedAt($raw->observedAt);
        }

        $this->em->persist($signal);

        return IngestOutcome::Created;
    }
}
