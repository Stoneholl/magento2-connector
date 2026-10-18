<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Stoneholl\Connector\Model\Api\ApiException;
use Stoneholl\Connector\Model\Api\Client;

class VerifiedLocalResetAvailability implements ArgumentInterface
{
    /** @var StateRepository */
    private $stateRepository;
    /** @var Client */
    private $client;

    /**
     * Initialize explicit dependencies for verified local reset availability.
     *
     * @param StateRepository $stateRepository
     * @param Client $client
     */
    public function __construct(StateRepository $stateRepository, Client $client)
    {
        $this->stateRepository = $stateRepository;
        $this->client = $client;
    }

    /**
     * Offer reset for rejected state when the API advertises verified reset.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        if ((string)$this->stateRepository->get()->getData('connection_status')
            !== ConnectionStatus::CREDENTIAL_REJECTED
        ) {
            return false;
        }

        try {
            $this->client->requireCapabilities(['verified_local_reset']);
        } catch (ApiException $exception) {
            return false;
        }

        return true;
    }
}
