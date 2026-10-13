<?php

namespace App\Tests\Signal;

use App\Enum\SignalType;
use App\Signal\RawSignal;
use App\Signal\StackFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StackFilterTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function titles(): iterable
    {
        yield 'symfony' => ['Développeur Symfony senior (H/F)', true];
        yield 'plusieurs technos collées' => ['Dev PHP/Laravel', true];
        yield 'devops' => ['Ingénieur DevOps Cloud', true];
        yield 'nuxt avec extension' => ['Développeur Nuxt.js', true];
        yield 'vue avec extension' => ['Front Vue.js', true];
        yield 'accents et casse' => ['DÉVELOPPEUR WORDPRESS', true];
        yield 'k3s' => ['Admin K3s', true];
        yield 'fullstack Java' => ['Développeur Full Stack Java / React H/F', false];
        yield 'fullstack .net' => ['Développeur Fullstack .net/ React.js (F/H)', false];
        yield 'mot contenant un mot-clé' => ['Développeur PHPStorm', false];
        yield 'avenue ne contient pas vue' => ['Revue de code Java', false];
    }

    #[DataProvider('titles')]
    public function testMatchesOnTitle(string $title, bool $expected): void
    {
        $filter = new StackFilter(['symfony', 'php', 'laravel', 'wordpress', 'vue', 'nuxt', 'docker', 'kubernetes', 'k3s', 'devops']);

        self::assertSame($expected, $filter->matches(new RawSignal(SignalType::JobPosting, 'test', 'fp', $title)));
    }

    public function testCategoryInUrlIsIgnored(): void
    {
        $signal = new RawSignal(SignalType::JobPosting, 'test', 'fp', 'Développeur Java', 'https://x.test/job/developpeur-front-end-react-angular-vue/dev-java-1', null, null, ['category' => 'developpeur-front-end-react-angular-vue']);

        self::assertFalse((new StackFilter(['vue']))->matches($signal));
    }
}
