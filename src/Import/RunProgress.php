<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Import;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Progress of a run: castor-api only returns the output of a task once it finishes, so dev-kit:apply writes its
 * steps to .castor/import/var/.progress/dev-kit.json (TaskProgress of opencolibri/starter-core).
 */
final class RunProgress
{
    /** Older files come from a step that did not end cleanly. */
    private const STALE_AFTER_SECONDS = 1800;

    public function __construct(
        #[Autowire('%opencolibri_dev_kit.import_var_dir%')]
        private readonly string $importVarDir,
    ) {}

    /**
     * The shop or the dataset a task works on, or null when the task reports no progress.
     *
     * @param array<string, mixed> $payload
     */
    public static function key(string $task, array $payload): ?string
    {
        // dev-kit:apply reports its steps as "dev-kit" (see DevKitTasks of opencolibri/starter-dev-kit).
        return 'dev-kit:apply' === $task ? 'dev-kit' : null;
    }

    /**
     * @return array{label: string, done: int, total: int, current: string|null, percent: int}|null
     */
    public function read(?string $key): ?array
    {
        if (null === $key) {
            return null;
        }

        $path = rtrim($this->importVarDir, '/\\') . '/.progress/' . $key . '.json';
        $json = is_file($path) ? @file_get_contents($path) : false;
        $data = false !== $json ? json_decode($json, true) : null;

        if (!\is_array($data) || (int) ($data['updatedAt'] ?? 0) < time() - self::STALE_AFTER_SECONDS) {
            return null;
        }

        $total = max(0, (int) ($data['total'] ?? 0));
        $done = min($total, max(0, (int) ($data['done'] ?? 0)));
        $current = \is_string($data['current'] ?? null) && '' !== $data['current'] ? $data['current'] : null;

        return [
            'label' => (string) ($data['label'] ?? ''),
            'done' => $done,
            'total' => $total,
            'current' => $current,
            'percent' => $total > 0 ? (int) floor($done * 100 / $total) : 0,
        ];
    }
}
