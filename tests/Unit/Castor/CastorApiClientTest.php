<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Tests\Unit\Castor;

use OpenColibri\Starter\DevKitBundle\Castor\CastorApiClient;
use OpenColibri\Starter\DevKitBundle\Castor\RunConflictException;
use OpenColibri\Starter\DevKitBundle\Tests\Support\FakeCastorApi;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CastorApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        FakeCastorApi::reset();
    }

    public function testItReadsTheStateOfTheShop(): void
    {
        $state = FakeCastorApi::client()->status();

        static::assertSame('Maison Roastery', $state['shop']['name'] ?? null);
        static::assertSame([['method' => 'POST', 'task' => 'dev-kit:status', 'action' => 'run', 'payload' => ['format' => 'json'], 'token' => 'secret-token']], FakeCastorApi::calls('dev-kit:status'));
    }

    public function testItSendsNoTokenWhenNoneIsConfigured(): void
    {
        FakeCastorApi::client(null)->status();
        FakeCastorApi::client('')->status();

        static::assertSame([null, null], array_column(FakeCastorApi::calls(), 'token'));
    }

    public function testItReadsTheCatalog(): void
    {
        $catalog = FakeCastorApi::client()->catalog();

        static::assertSame(['canvas', 'roastery', 'drive'], array_column($catalog['themes'], 'name'));
        static::assertSame(['roastery', 'teahouse', 'my-catalog'], array_column($catalog['datasets'], 'slug'));
        static::assertSame(['altcha', 'recaptcha', 'refund', 'wishlist'], array_column($catalog['plugins'], 'name'));
    }

    public function testItReadsTheJsonAfterTheNoticesOfCastor(): void
    {
        FakeCastorApi::output('dev-kit:history', "A new Castor version is available.\n[{\"at\": \"2026-11-23\", \"state\": {}}]");

        static::assertSame([['at' => '2026-11-23', 'state' => []]], FakeCastorApi::client()->history());
    }

    public function testItAsksForThePlanOfAState(): void
    {
        $plan = FakeCastorApi::client()->plan(['theme' => ['name' => 'drive']]);

        static::assertSame(['Change the header layout'], array_column($plan['steps'], 'title'));
        static::assertSame([], $plan['errors']);
        static::assertSame([['manifest-json' => '{"theme":{"name":"drive"}}', 'dry-run' => true, 'format' => 'json']], array_column(FakeCastorApi::calls('dev-kit:apply'), 'payload'));
    }

    public function testItReturnsTheReasonsAStateCannotBeReached(): void
    {
        // dev-kit:apply exits 1 and prints its errors as JSON: castor-api answers 422.
        $client = new CastorApiClient(new MockHttpClient(new MockResponse(json_encode([
            'status' => 'failed',
            'exitCode' => 1,
            'stdout' => '{"errors":["Unknown theme \"nope\"."]}',
            'stderr' => '',
        ], \JSON_THROW_ON_ERROR), ['http_code' => 422])), 'http://castor-api.test', null);

        static::assertSame(['steps' => [], 'errors' => ['Unknown theme "nope".']], $client->plan(['theme' => ['name' => 'nope']]));
    }

    public function testTheApplyPayloadRunsWithoutAsking(): void
    {
        static::assertSame(['manifest-json' => '{"plugins":["altcha"]}', 'yes' => true], CastorApiClient::applyPayload(['plugins' => ['altcha']]));
    }

    public function testItReportsTheOutputOfAFailedTask(): void
    {
        FakeCastorApi::fail('dev-kit:status', 'The WEB_STORE channel was not found: load the fixtures first.');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The WEB_STORE channel was not found');

        FakeCastorApi::client()->status();
    }

    public function testItStartsARun(): void
    {
        static::assertSame(['id' => 'run-1', 'task' => 'dev-kit:apply', 'status' => 'pending'], FakeCastorApi::client()->start('dev-kit:apply', ['yes' => true]));
    }

    public function testItReportsTheRunThatHoldsTheLock(): void
    {
        $client = new CastorApiClient(new MockHttpClient(new MockResponse(json_encode([
            'error' => 'A task is running.',
            'conflict' => ['task' => 'sylius:dataset:load', 'payload' => ['dataset' => 'roastery']],
        ], \JSON_THROW_ON_ERROR), ['http_code' => 409])), 'http://castor-api.test', null);

        try {
            $client->start('dev-kit:apply', ['yes' => true]);
            static::fail('No conflict reported.');
        } catch (RunConflictException $exception) {
            static::assertSame('sylius:dataset:load', $exception->conflict['task']);
        }
    }

    public function testItReportsAnUnreachableCastorApi(): void
    {
        $client = new CastorApiClient(new MockHttpClient(static fn() => throw new TransportException('Connection refused')), 'http://castor-api.test/', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to reach castor-api at http://castor-api.test: Connection refused');

        $client->status();
    }
}
