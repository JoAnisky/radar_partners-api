<?php

namespace App\Tests\Api;

use App\Enum\PipelineStatus;
use App\Enum\SignalType;

final class SignalApiTest extends ApiTestCase
{
    /**
     * @param array<string, mixed> $body
     *
     * @return list<string>
     */
    private static function titles(array $body): array
    {
        /** @var list<array{title: string}> $items */
        $items = self::at($body, 'items');

        return array_map(static fn (array $item): string => $item['title'], $items);
    }

    public function testListsNewestFirstAndFiltersUnresolved(): void
    {
        $this->signal('Old', detectedAt: '-3 days');
        $attached = $this->signal('Attached', detectedAt: '-1 day');
        $attached->setCompany($this->company('Acme'));
        $this->signal('Fresh', detectedAt: '-1 hour');
        $this->em->flush();

        $all = $this->get('/api/signals')[1];
        self::assertSame(3, self::at($all, 'total'));
        self::assertSame(['Fresh', 'Attached', 'Old'], self::titles($all));

        $unresolved = $this->get('/api/signals?unresolved=1')[1];
        self::assertSame(2, self::at($unresolved, 'total'));
        self::assertSame(['Fresh', 'Old'], self::titles($unresolved));

        /** @var list<array{title: string, companyName: ?string}> $items */
        $items = self::at($all, 'items');
        self::assertSame('Acme', $items[1]['companyName']);
    }

    public function testPaginationAndValidation(): void
    {
        foreach (['A', 'B', 'C'] as $i => $title) {
            $this->signal($title, detectedAt: sprintf('-%d hours', $i + 1));
        }

        self::assertSame(['C'], self::titles($this->get('/api/signals?perPage=2&page=2')[1]));
        self::assertSame(422, $this->get('/api/signals?perPage=0')[0]);
        self::assertSame(422, $this->get('/api/signals?page=-1')[0]);
    }

    public function testAttachAndDetachCompany(): void
    {
        $signal = $this->signal('Offer');
        $company = $this->company('Acme');

        [$status, $body] = $this->request('PATCH', '/api/signals/'.$signal->getId(), ['companyId' => $company->getId()]);
        self::assertSame(200, $status);
        self::assertSame($company->getId(), self::at($body, 'companyId'));
        self::assertSame('Acme', self::at($body, 'companyName'));

        $detail = $this->get('/api/companies/'.$company->getId())[1];
        /** @var list<array{title: string}> $signals */
        $signals = self::at($detail, 'signals');
        self::assertSame('Offer', $signals[0]['title']);

        [, $detached] = $this->request('PATCH', '/api/signals/'.$signal->getId(), ['companyId' => null]);
        self::assertNull(self::at($detached, 'companyId'));
    }

    public function testAttachingAnOfferToAnOnHoldCompanyBringsItBack(): void
    {
        $company = $this->company('Acme')->setStatus(PipelineStatus::OnHold)->setRecontactAfter(new \DateTimeImmutable('+5 months'));
        $this->em->flush();
        self::assertSame([], self::names($this->get('/api/companies?due=1')[1]));

        $this->request('PATCH', '/api/signals/'.$this->signal('Offer')->getId(), ['companyId' => $company->getId()]);

        self::assertSame(['Acme'], self::names($this->get('/api/companies?due=1')[1]));
    }

    public function testOtherSignalTypesDoNotBringACompanyBack(): void
    {
        $company = $this->company('Acme')->setStatus(PipelineStatus::OnHold)->setRecontactAfter(new \DateTimeImmutable('+5 months'));
        $this->em->flush();

        $this->request('PATCH', '/api/signals/'.$this->signal('Stack', SignalType::StackDetected)->getId(), ['companyId' => $company->getId()]);

        self::assertSame([], self::names($this->get('/api/companies?due=1')[1]));
    }

    public function testDismissedSignalsLeaveTheListAndShowInArchive(): void
    {
        $this->signal('Kept');
        $dismissed = $this->signal('Dismissed');

        [$status, $body] = $this->request('PATCH', '/api/signals/'.$dismissed->getId(), ['dismissed' => true]);
        self::assertSame(200, $status);
        self::assertNotNull(self::at($body, 'dismissedAt'));

        self::assertSame(['Kept'], self::titles($this->get('/api/signals?unresolved=1')[1]));
        self::assertSame(['Dismissed'], self::titles($this->get('/api/signals?dismissed=1')[1]));

        $this->request('PATCH', '/api/signals/'.$dismissed->getId(), ['dismissed' => false]);
        self::assertSame(2, self::at($this->get('/api/signals')[1], 'total'));
        self::assertSame([], self::titles($this->get('/api/signals?dismissed=1')[1]));
    }

    public function testAttachedSignalCannotBeDismissed(): void
    {
        $company = $this->company('Acme');
        $signal = $this->signal('Offer');
        $this->request('PATCH', '/api/signals/'.$signal->getId(), ['companyId' => $company->getId()]);

        self::assertSame(422, $this->request('PATCH', '/api/signals/'.$signal->getId(), ['dismissed' => true])[0]);
    }

    public function testAttachingRestoresADismissedSignal(): void
    {
        $company = $this->company('Acme');
        $signal = $this->signal('Offer');
        $this->request('PATCH', '/api/signals/'.$signal->getId(), ['dismissed' => true]);

        $this->request('PATCH', '/api/signals/'.$signal->getId(), ['companyId' => $company->getId()]);

        self::assertSame(['Offer'], self::titles($this->get('/api/signals')[1]));
    }

    public function testDismissRejectsInvalidValues(): void
    {
        $uri = '/api/signals/'.$this->signal('Offer')->getId();

        foreach ([['dismissed' => 'yes'], ['dismissed' => null], ['dismissed' => true, 'companyId' => null]] as $payload) {
            self::assertSame(422, $this->request('PATCH', $uri, $payload)[0], (string) json_encode($payload));
        }
    }

    public function testAttachRejectsInvalidPayloads(): void
    {
        $uri = '/api/signals/'.$this->signal('Offer')->getId();

        foreach ([[], ['companyId' => 999999], ['companyId' => 'abc'], ['companyId' => null, 'extra' => 1], ['other' => 1]] as $payload) {
            self::assertSame(422, $this->request('PATCH', $uri, $payload)[0], (string) json_encode($payload));
        }
    }

    public function testAttachUnknownSignalIsNotFound(): void
    {
        self::assertSame(404, $this->request('PATCH', '/api/signals/999999', ['companyId' => null])[0]);
    }
}
