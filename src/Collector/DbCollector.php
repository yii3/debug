<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Panel\Db\{DbSnapshot, QueryRow};
use Throwable;

use function array_pop;
use function count;

/**
 * Retains completed database observations only within the current debugger request lifecycle.
 */
final class DbCollector implements CollectorInterface
{
    /**
     * Stores a failure to rethrow when the snapshot is captured.
     */
    private Throwable|null $failure = null;
    /**
     * Stores active observations grouped by query token.
     *
     * @var array<string, list<array{time: float, trace: list<array<string, mixed>>}>>
     */
    private array $pending = [];
    /**
     * Stores the row count reported by the current statement.
     */
    private int|null $rowCount = null;
    /**
     * Stores completed database observations in sequence order.
     *
     * @var list<QueryRow>
     */
    private array $rows = [];
    /**
     * Indicates whether collection is active for the current request lifecycle.
     */
    private bool $started = false;

    /**
     * @param list<array<string, mixed>> $trace
     */
    public function begin(string $token, float $time, array $trace): void
    {
        if ($this->started) {
            $this->pending[$token][] = ['time' => $time, 'trace' => $trace];
        }
    }

    public function capture(): DbSnapshot|null
    {
        if (!$this->started) {
            return null;
        }

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return DbSnapshot::capture($this->rows);
    }

    public function end(string $token, float $time): void
    {
        if (!$this->started || ($this->pending[$token] ?? []) === []) {
            return;
        }

        $begin = array_pop($this->pending[$token]);

        $rowCount = $this->rowCount;

        $this->rowCount = null;

        $this->observe(
            QueryRow::create($token, ($time - $begin['time']) * 1000, $begin['time'] * 1000)
                ->withTrace($begin['trace'])
                ->withRows($rowCount),
        );
    }

    public function id(): string
    {
        return 'db';
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function observe(QueryRow $row): void
    {
        if ($this->started) {
            $this->rows[] = $row->withSequence(count($this->rows));
        }
    }

    public function reportFailure(Throwable $failure): void
    {
        $this->failure = $failure;
    }

    /**
     * Records the row count reported by the statement that is about to complete.
     *
     * The last report before {@see end()} wins, so a retry handler re-executing the same statement overrides the count
     * of the failed attempt.
     *
     * @param int|null $rows Rows returned or affected, or `null` when the statement did not complete.
     */
    public function reportRows(int|null $rows): void
    {
        $this->rowCount = $rows;
    }

    public function shutdown(): void
    {
        $this->started = false;
        $this->rows = [];
        $this->pending = [];
        $this->rowCount = null;
        $this->failure = null;
    }

    public function startup(): void
    {
        if ($this->started) {
            return;
        }

        $this->rows = [];
        $this->pending = [];
        $this->rowCount = null;
        $this->failure = null;
        $this->started = true;
    }
}
