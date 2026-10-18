<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Magento\Framework\Serialize\Serializer\Json;
use Stoneholl\Connector\Model\ResourceModel\State as StateResource;

class StateRepository
{
    private const SINGLETON_ID = 1;

    /** @var StateFactory */
    private $stateFactory;

    /** @var StateResource */
    private $resource;

    /** @var Json */
    private $json;

    /**
     * Initialize explicit dependencies for state repository.
     *
     * @param StateFactory $stateFactory
     * @param StateResource $resource
     * @param Json $json
     */
    public function __construct(StateFactory $stateFactory, StateResource $resource, Json $json)
    {
        $this->stateFactory = $stateFactory;
        $this->resource = $resource;
        $this->json = $json;
    }

    /**
     * Load the singleton cached state, defaulting an absent row to unpaired.
     *
     * @return State
     */
    public function get(): State
    {
        $state = $this->stateFactory->create();
        $this->resource->load($state, self::SINGLETON_ID);
        if (!$state->getId()) {
            $state->setData('connection_status', ConnectionStatus::UNPAIRED);
        }
        return $state;
    }

    /**
     * Persist the singleton without creating duplicate rows on initial writes.
     *
     * @param State $state
     * @return void
     */
    public function save(State $state): void
    {
        if (!$state->getId()) {
            $data = $state->getData();
            $data['state_id'] = self::SINGLETON_ID;
            $this->resource->getConnection()->insertOnDuplicate(
                $this->resource->getMainTable(),
                $data,
                array_keys($data)
            );
            $state->setId(self::SINGLETON_ID);
            return;
        }
        $this->resource->save($state);
    }

    /**
     * Record the confirmed installation identity while clearing obsolete failure messages.
     *
     * @param string $installationId
     * @param string $environment
     * @param string $domain
     * @return void
     */
    public function markPaired(string $installationId, string $environment, string $domain): void
    {
        $state = $this->get();
        $state->addData([
            'connection_status' => ConnectionStatus::CONNECTED,
            'installation_id' => $installationId,
            'environment' => $environment,
            'domain' => $domain,
            'last_error_code' => null,
            'last_error_message' => null,
        ]);
        $this->save($state);
    }

    /**
     * Retain installation identity while confirmation remains incomplete.
     *
     * @param string $installationId
     * @param string $environment
     * @param string $domain
     * @return void
     */
    public function markPairingPending(string $installationId, string $environment, string $domain): void
    {
        $state = $this->get();
        $state->addData([
            'connection_status' => ConnectionStatus::PAIRING_CONFIRMATION_PENDING,
            'installation_id' => $installationId,
            'environment' => $environment,
            'domain' => $domain,
            'last_error_code' => null,
            'last_error_message' => null,
        ]);
        $this->save($state);
    }

    /**
     * Record the attempted check without replacing the last successful snapshot.
     *
     * @return void
     */
    public function markAttempt(): void
    {
        $state = $this->get();
        $state->setData('last_attempt_at', gmdate('Y-m-d H:i:s'));
        $this->save($state);
    }

    /**
     * Atomically persist a complete normalized snapshot and its success timestamps.
     *
     * @param array $snapshot
     * @return void
     */
    public function markSuccess(array $snapshot): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $state = $this->get();
        $state->addData([
            'connection_status' => ConnectionStatus::CONNECTED,
            'last_attempt_at' => $now,
            'last_success_at' => $now,
            'last_error_code' => null,
            'last_error_message' => null,
            'snapshot_json' => $this->json->serialize($snapshot),
        ]);
        $this->save($state);
    }

    /**
     * Record bounded safe diagnostics while preserving the last successful snapshot.
     *
     * @param string $status
     * @param string $code
     * @param string $message
     * @return void
     */
    public function markFailure(string $status, string $code, string $message): void
    {
        $state = $this->get();
        $state->addData([
            'connection_status' => $status,
            'last_attempt_at' => gmdate('Y-m-d H:i:s'),
            'last_error_code' => substr($code, 0, 64),
            'last_error_message' => substr($message, 0, 2048),
        ]);
        $this->save($state);
    }

    /**
     * Clear local connection identity and cached terms after authorized cleanup.
     *
     * @return void
     */
    public function disconnect(): void
    {
        $state = $this->get();
        $state->addData([
            'connection_status' => ConnectionStatus::UNPAIRED,
            'installation_id' => null,
            'environment' => null,
            'domain' => null,
            'last_attempt_at' => null,
            'last_success_at' => null,
            'last_error_code' => null,
            'last_error_message' => null,
            'snapshot_json' => null,
        ]);
        $this->save($state);
    }

    /**
     * Deserialize cached data, returning an empty array for absent or invalid JSON.
     *
     * @param State $state
     * @return array
     */
    public function getSnapshot(State $state): array
    {
        $json = (string)$state->getData('snapshot_json');
        if ($json === '') {
            return [];
        }
        try {
            $data = $this->json->unserialize($json);
        } catch (\InvalidArgumentException $exception) {
            return [];
        }
        return is_array($data) ? $data : [];
    }
}
