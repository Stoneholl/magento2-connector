<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Cron;

use Psr\Log\LoggerInterface;
use Stoneholl\Connector\Model\Api\ApiException;
use Stoneholl\Connector\Model\Config;
use Stoneholl\Connector\Model\ConnectionStatus;
use Stoneholl\Connector\Model\CredentialStorage;
use Stoneholl\Connector\Model\StateRepository;
use Stoneholl\Connector\Model\Synchronizer;

class Refresh
{
    /** @var Config */
    private $config;
    /** @var CredentialStorage */
    private $credentialStorage;
    /** @var StateRepository */
    private $stateRepository;
    /** @var Synchronizer */
    private $synchronizer;
    /** @var LoggerInterface */
    private $logger;

    /**
     * Initialize explicit dependencies for refresh.
     *
     * @param Config $config
     * @param CredentialStorage $credentialStorage
     * @param StateRepository $stateRepository
     * @param Synchronizer $synchronizer
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        CredentialStorage $credentialStorage,
        StateRepository $stateRepository,
        Synchronizer $synchronizer,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->credentialStorage = $credentialStorage;
        $this->stateRepository = $stateRepository;
        $this->synchronizer = $synchronizer;
        $this->logger = $logger;
    }

    /**
     * Refresh only when automatic checks are enabled and the configured interval has elapsed.
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            if (!$this->config->automaticChecksEnabled() || !$this->credentialStorage->has()) {
                return;
            }
            $state = $this->stateRepository->get();
            if ($state->getData('connection_status') === ConnectionStatus::PAIRING_CONFIRMATION_PENDING) {
                return;
            }
            $lastAttempt = strtotime((string)$state->getData('last_attempt_at'));
            if ($lastAttempt && $lastAttempt > time() - ($this->config->getCheckIntervalHours() * 3600)) {
                return;
            }
            $this->synchronizer->execute();
        } catch (ApiException $exception) {
            $this->logger->warning(
                'Stoneholl Connector automatic refresh did not complete: {code}',
                ['code' => $exception->getApiCode()]
            );
        }
    }
}
