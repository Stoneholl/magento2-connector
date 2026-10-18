<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Stoneholl\Connector\Model\Api\ApiException;
use Stoneholl\Connector\Model\Api\Client;

class DisconnectService
{
    /** @var Client */
    private $client;
    /** @var CredentialStorage */
    private $credentialStorage;
    /** @var LocalForgetService */
    private $localForgetService;

    /**
     * Initialize explicit dependencies for disconnect service.
     *
     * @param Client $client
     * @param CredentialStorage $credentialStorage
     * @param LocalForgetService $localForgetService
     */
    public function __construct(
        Client $client,
        CredentialStorage $credentialStorage,
        LocalForgetService $localForgetService
    ) {
        $this->client = $client;
        $this->credentialStorage = $credentialStorage;
        $this->localForgetService = $localForgetService;
    }

    /**
     * Unlink on the server before deleting the saved local connection.
     *
     * @return void
     */
    public function execute(): void
    {
        $credential = $this->credentialStorage->get();
        if ($credential === null) {
            throw new ApiException('credential_missing', 'This Magento installation is not paired.');
        }
        $this->client->requireCapabilities(['installation_self_unlink']);
        $this->client->unlink($credential);
        $this->localForgetService->execute();
    }
}
