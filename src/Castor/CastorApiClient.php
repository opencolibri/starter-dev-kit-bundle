<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Castor;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The dev-kit:* tasks of opencolibri/starter-dev-kit, run by castor-api next to the local Sylius app.
 */
final class CastorApiClient
{
    public const string STATUS = 'dev-kit:status';
    public const string CATALOG = 'dev-kit:catalog';
    public const string HISTORY = 'dev-kit:history';
    public const string APPLY = 'dev-kit:apply';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        /** Null or empty: castor-api runs without authentication (%env(default::CASTOR_API_TOKEN)%). */
        private readonly ?string $token,
    ) {}

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function run(string $task, array $payload = []): array
    {
        $data = $this->request('POST', self::taskPath($task, '/run'), $payload);

        if (self::isFailedTaskPayload($data)) {
            throw new \RuntimeException(self::taskFailureMessage($data));
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{id: string, task: string, status: string}
     */
    public function start(string $task, array $payload = []): array
    {
        $data = $this->request('POST', self::taskPath($task, '/start'), $payload);
        $id = (string) ($data['id'] ?? '');

        if ('' === $id) {
            throw new \RuntimeException('castor-api did not return a run id.');
        }

        return [
            'id' => $id,
            'task' => (string) ($data['task'] ?? $task),
            'status' => (string) ($data['status'] ?? 'pending'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runStatus(string $task, string $runId): array
    {
        return $this->request('GET', self::taskPath($task, '/status/' . rawurlencode($runId)));
    }

    /**
     * The async runs that are pending or running, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    public function listActiveRuns(): array
    {
        $runs = $this->request('GET', '/runs')['runs'] ?? [];

        return \is_array($runs) ? array_values(array_filter($runs, \is_array(...))) : [];
    }

    /**
     * What the local shop runs now (dev-kit:status): the manifest form of its state.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return self::decodeMap($this->run(self::STATUS, ['format' => 'json']), 'status');
    }

    /**
     * What a manifest can pick (dev-kit:catalog): themes, datasets, plugins.
     *
     * @return array{themes: list<array<string, mixed>>, datasets: list<array<string, mixed>>, plugins: list<array<string, mixed>>}
     */
    public function catalog(): array
    {
        $catalog = self::decodeMap($this->run(self::CATALOG), 'catalog');

        return [
            'themes' => self::maps($catalog['themes'] ?? null),
            'datasets' => self::maps($catalog['datasets'] ?? null),
            'plugins' => self::maps($catalog['plugins'] ?? null),
        ];
    }

    /**
     * The states dev-kit:apply brought the shop to, the latest first.
     *
     * @return list<array<string, mixed>>
     */
    public function history(): array
    {
        return self::maps(self::decodeJsonOutput($this->run(self::HISTORY)));
    }

    /**
     * The steps that bring the shop to this state, or the reasons it cannot be reached.
     *
     * @param array<string, mixed> $manifest
     *
     * @return array{steps: list<array<string, mixed>>, errors: list<string>}
     */
    public function plan(array $manifest): array
    {
        // A refused manifest is a failed task (HTTP 422) whose output lists the errors: read it, do not throw.
        $data = $this->request('POST', self::taskPath(self::APPLY, '/run'), [
            'manifest-json' => json_encode($manifest, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            'dry-run' => true,
            'format' => 'json',
        ]);
        $plan = self::decodeJsonOutput($data);

        if (!\is_array($plan)) {
            throw new \RuntimeException(self::taskFailureMessage($data));
        }

        return [
            'steps' => self::maps($plan['steps'] ?? null),
            'errors' => self::strings($plan['errors'] ?? null),
        ];
    }

    /**
     * Starts bringing the shop to this state: the run RunMonitor follows.
     *
     * @param array<string, mixed> $manifest
     *
     * @return array<string, mixed> the payload of the run
     */
    public static function applyPayload(array $manifest): array
    {
        return [
            'manifest-json' => json_encode($manifest, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            'yes' => true,
        ];
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private static function decodeMap(array $result, string $what): array
    {
        $decoded = self::decodeJsonOutput($result);

        if (!\is_array($decoded) || array_is_list($decoded) && [] !== $decoded) {
            throw new \RuntimeException(\sprintf('castor-api returned an invalid %s.', $what));
        }

        return $decoded;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function maps(mixed $values): array
    {
        return array_values(array_filter(\is_array($values) ? $values : [], \is_array(...)));
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        return array_values(array_filter(\is_array($values) ? $values : [], \is_string(...)));
    }

    /**
     * Castor may print notices (e.g. "A new Castor version is available") before the JSON output.
     *
     * @param array<string, mixed> $result
     */
    private static function decodeJsonOutput(array $result): mixed
    {
        $stdout = trim((string) ($result['stdout'] ?? ''));
        if (!preg_match('/^[\[{]/m', $stdout, $match, \PREG_OFFSET_CAPTURE)) {
            return null;
        }

        try {
            return json_decode(substr($stdout, $match[0][1]), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    private static function taskPath(string $task, string $suffix): string
    {
        return '/tasks/' . rawurlencode($task) . $suffix;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $base = rtrim($this->baseUrl, '/');
        $options = [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        if (null !== $this->token && '' !== $this->token) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->token;
        }

        if ('GET' !== $method) {
            $options['json'] = $payload;
        }

        try {
            $response = $this->httpClient->request($method, $base . $path, $options);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException(\sprintf('Unable to reach castor-api at %s: %s', $base, $exception->getMessage()), 0, $exception);
        }

        // 422 is a failed Castor task with the same JSON body as a success.
        if (422 === $status) {
            return $data;
        }

        if (409 === $status && \is_array($data['conflict'] ?? null)) {
            throw new RunConflictException(\is_string($data['error'] ?? null) ? $data['error'] : 'Another task is running.', $data['conflict']);
        }

        if ($status >= 400) {
            $message = \is_string($data['error'] ?? null) ? $data['error'] : 'castor-api error (HTTP ' . $status . ').';

            throw new \RuntimeException($message);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function isFailedTaskPayload(array $data): bool
    {
        if ('failed' === ($data['status'] ?? null)) {
            return true;
        }

        $exitCode = $data['exitCode'] ?? null;

        return \is_int($exitCode) && 0 !== $exitCode;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function taskFailureMessage(array $data): string
    {
        foreach (['stderr', 'stdout', 'error'] as $key) {
            $value = ConsoleOutput::withoutProgressBars(trim((string) ($data[$key] ?? '')));

            if ('' !== $value) {
                return $value;
            }
        }

        $exitCode = $data['exitCode'] ?? null;

        return \is_int($exitCode)
            ? \sprintf('castor-api task failed (exit code %d).', $exitCode)
            : 'castor-api task failed.';
    }
}
