<?php

namespace App\Tests\Signal;

use App\Enum\SignalType;
use App\Signal\RawSignal;
use App\Signal\StackFilter;
use App\Signal\StackMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StackFilterTest extends TestCase
{
    private const OTHERS = ['java', 'net', 'react', 'odoo', 'python'];

    /** @return iterable<string, array{string, StackMatch}> */
    public static function titles(): iterable
    {
        yield 'symfony' => ['Développeur Symfony senior (H/F)', StackMatch::Match];
        yield 'plusieurs technos collées' => ['Dev PHP/Laravel', StackMatch::Match];
        yield 'devops' => ['Ingénieur DevOps Cloud', StackMatch::Match];
        yield 'nuxt avec extension' => ['Développeur Nuxt.js', StackMatch::Match];
        yield 'vue avec extension' => ['Front Vue.js', StackMatch::Match];
        yield 'accents et casse' => ['DÉVELOPPEUR WORDPRESS', StackMatch::Match];
        yield 'k3s' => ['Admin K3s', StackMatch::Match];
        yield 'fullstack Java' => ['Développeur Full Stack Java / React H/F', StackMatch::Excluded];
        yield 'fullstack .net' => ['Développeur Fullstack .net/ React.js (F/H)', StackMatch::Excluded];
        yield 'mot contenant un mot-clé' => ['Développeur PHPStorm', StackMatch::Review];
        yield 'aucune techno' => ['Développeur Freelance', StackMatch::Review];
        yield 'techno connue hors stack' => ['Développeur Odoo (Lyon)', StackMatch::Excluded];
        yield 'stack et autre techno' => ['Dev Symfony / React', StackMatch::Match];
        yield 'avenue ne contient pas vue' => ['Revue de code Java', StackMatch::Excluded];
    }

    #[DataProvider('titles')]
    public function testClassifiesOnTitle(string $title, StackMatch $expected): void
    {
        $filter = new StackFilter(['symfony', 'php', 'laravel', 'wordpress', 'vue', 'nuxt', 'docker', 'kubernetes', 'k3s', 'devops'], self::OTHERS);

        self::assertSame($expected, $filter->classify(new RawSignal(SignalType::JobPosting, 'test', 'fp', $title)));
    }

    public function testCategoryInUrlIsIgnored(): void
    {
        $signal = new RawSignal(SignalType::JobPosting, 'test', 'fp', 'Développeur Java', 'https://x.test/job/developpeur-front-end-react-angular-vue/dev-java-1', null, null, ['category' => 'developpeur-front-end-react-angular-vue']);

        self::assertSame(StackMatch::Excluded, (new StackFilter(['vue'], ['java']))->classify($signal));
    }
}
