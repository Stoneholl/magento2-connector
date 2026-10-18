<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Plugin;

use Magento\Framework\Lock\LockManagerInterface;
use Stoneholl\Connector\Model\Api\ApiException;

/**
 * Serialize connection mutations across Admin requests and cron before any state is read.
 *
 * The shared plugin instance permits nested cleanup/confirmation in one operation. Existing
 * refresh/rotation services acquire this same key directly. A single native lock is
 * held by Connector at a time. DI gives Connector its own configured backend instance
 * so Magento 2.3's scheduler can retain its independent job lock.
 */
class ConnectionOperationGuard
{
    private const LOCK_NAME = 'stoneholl_connector_operation';

    /** @var LockManagerInterface */
    private $lockManager;

    /** @var int */
    private $depth = 0;

    /**
     * Use Magento's configured process-safe lock backend for this installation.
     *
     * @param LockManagerInterface $lockManager
     */
    public function __construct(LockManagerInterface $lockManager)
    {
        $this->lockManager = $lockManager;
    }

    /**
     * Guard disconnect and cleanup before loading identity or credentials.
     *
     * @param object $subject
     * @param callable $proceed
     * @return mixed
     */
    public function aroundExecute($subject, callable $proceed)
    {
        return $this->run($proceed);
    }

    /**
     * Guard a new pairing before domain, credentials or saved workflows are read.
     *
     * @param object $subject
     * @param callable $proceed
     * @param string $code
     * @param string $environment
     * @return void
     */
    public function aroundPair($subject, callable $proceed, string $code, string $environment): void
    {
        $this->run($proceed, [$code, $environment]);
    }

    /**
     * Guard recovery before loading the installation identity.
     *
     * @param object $subject
     * @param callable $proceed
     * @param string $code
     * @return void
     */
    public function aroundRecover($subject, callable $proceed, string $code): void
    {
        $this->run($proceed, [$code]);
    }

    /**
     * Guard confirmation, including reentrant confirmation inside a pairing operation.
     *
     * @param object $subject
     * @param callable $proceed
     * @return void
     */
    public function aroundConfirmPending($subject, callable $proceed): void
    {
        $this->run($proceed);
    }

    /**
     * Hold one shared lock through nested operations and release it after any throwable.
     *
     * @param callable $operation
     * @param array $arguments
     * @return mixed
     * @throws ApiException
     */
    private function run(
        callable $operation,
        array $arguments = []
    ) {
        if ($this->depth === 0 && !$this->lockManager->lock(self::LOCK_NAME, 0)) {
            throw new ApiException(
                'operation_in_progress',
                'Another Connector operation is running. Try again after it finishes.'
            );
        }
        $this->depth++;
        try {
            return $operation(...$arguments);
        } finally {
            $this->depth--;
            if ($this->depth === 0) {
                $this->lockManager->unlock(self::LOCK_NAME);
            }
        }
    }
}
