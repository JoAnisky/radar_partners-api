<?php

namespace App\Tests\Api;

final class HealthApiTest extends ApiTestCase
{
    public function testHealthAnswersOk(): void
    {
        [$status, $body] = $this->get('/api/health');

        self::assertSame(200, $status);
        self::assertSame(['status' => 'ok'], $body);
    }
}
