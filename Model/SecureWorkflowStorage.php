<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Stoneholl\Connector\Model\Api\ApiException;

class SecureWorkflowStorage
{
    private const PAIRING_PATH = 'stoneholl_connector/connection/pairing_attempt';
    private const ROTATION_PATH = 'stoneholl_connector/connection/credential_rotation';

    /** @var ScopeConfigInterface */
    private $scopeConfig;
    /** @var WriterInterface */
    private $writer;
    /** @var EncryptorInterface */
    private $encryptor;
    /** @var Json */
    private $json;
    /** @var ReinitableConfigInterface */
    private $reinitableConfig;

    /**
     * Initialize explicit dependencies for secure workflow storage.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $writer
     * @param EncryptorInterface $encryptor
     * @param Json $json
     * @param ReinitableConfigInterface $reinitableConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $writer,
        EncryptorInterface $encryptor,
        Json $json,
        ReinitableConfigInterface $reinitableConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->writer = $writer;
        $this->encryptor = $encryptor;
        $this->json = $json;
        $this->reinitableConfig = $reinitableConfig;
    }

    /**
     * Decrypt the recoverable pairing draft without exposing it to presentation code.
     *
     * @return array|null
     */
    public function getPairing(): ?array
    {
        return $this->get(self::PAIRING_PATH);
    }

    /**
     * Encrypt and persist the complete pairing draft before continuing its workflow.
     *
     * @param array $data
     * @return void
     */
    public function savePairing(array $data): void
    {
        $this->save(self::PAIRING_PATH, $data);
    }

    /**
     * Remove only the pairing draft after confirmed completion or authorized cleanup.
     *
     * @return void
     */
    public function deletePairing(): void
    {
        $this->delete(self::PAIRING_PATH);
    }

    /**
     * Decrypt the saved issuance marker or received replacement draft.
     *
     * @return array|null
     */
    public function getRotation(): ?array
    {
        return $this->get(self::ROTATION_PATH);
    }

    /**
     * Encrypt and persist rotation progress before the next irreversible server step.
     *
     * @param array $data
     * @return void
     */
    public function saveRotation(array $data): void
    {
        $this->save(self::ROTATION_PATH, $data);
    }

    /**
     * Remove the rotation draft after completion or authorized connection cleanup.
     *
     * @return void
     */
    public function deleteRotation(): void
    {
        $this->delete(self::ROTATION_PATH);
    }

    /**
     * Decrypt and validate a workflow, failing closed when stored data cannot be read.
     *
     * @param string $path
     * @return array|null
     */
    private function get(string $path): ?array
    {
        $encrypted = (string)$this->scopeConfig->getValue($path);
        if ($encrypted === '') {
            return null;
        }
        try {
            $decoded = $this->json->unserialize($this->encryptor->decrypt($encrypted));
        } catch (\Throwable $exception) {
            throw new ApiException(
                'workflow_state_unreadable',
                'The encrypted Connector workflow state is unreadable.'
            );
        }
        if (!is_array($decoded)) {
            throw new ApiException('workflow_state_unreadable', 'The encrypted Connector workflow state is invalid.');
        }
        return $decoded;
    }

    /**
     * Serialize and encrypt workflow data before writing Default-scope configuration.
     *
     * @param string $path
     * @param array $data
     * @return void
     */
    private function save(string $path, array $data): void
    {
        $this->writer->save(
            $path,
            $this->encryptor->encrypt($this->json->serialize($data)),
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0
        );
        $this->reinitableConfig->reinit();
    }

    /**
     * Remove one workflow setting and refresh the local configuration view.
     *
     * @param string $path
     * @return void
     */
    private function delete(string $path): void
    {
        $this->writer->delete($path, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);
        $this->reinitableConfig->reinit();
    }
}
