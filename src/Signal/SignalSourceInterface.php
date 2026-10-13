<?php

namespace App\Signal;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.signal_source')]
interface SignalSourceInterface
{
    public function getName(): string;

    /** @return iterable<RawSignal> */
    public function fetch(\DateTimeImmutable $since): iterable;
}
