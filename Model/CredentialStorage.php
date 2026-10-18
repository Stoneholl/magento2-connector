<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Stoneholl\Connector\Model\Api\ApiException;

class CredentialStorage
{
    private const PATH = 'stoneholl_connector/connection/credential';

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var WriterInterface */
    private $writer;

    /** @var EncryptorInterface */
    private $encryptor;

    /** @var ReinitableConfigInterface */
    private $reinitableConfig;

    /**
     * Initialize explicit dependencies for credential storage.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $writer
     * @param EncryptorInterface $encryptor
     * @param ReinitableConfigInterface $reinitableConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $writer,
        EncryptorInterface $encryptor,
        ReinitableConfigInterface $reinitableConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->writer = $writer;
        $this->encryptor = $encryptor;
        $this->reinitableConfig = $reinitableConfig;
    }

    /**
     * Report whether a readable nonempty installation credential is configured.
     *
     * @return bool
     */
    public function has(): bool
    {
        return $this->get() !== null;
    }

    /**
     * Decrypt the installation credential and fail closed on unreadable ciphertext.
     *
     * @return string|null
     */
    public function get(): ?string
    {
        $encrypted = (string)$this->scopeConfig->getValue(self::PATH);
        if ($encrypted === '') {
            return null;
        }
        try {
            $value = $this->encryptor->decrypt($encrypted);
        } catch (\Throwable $exception) {
            throw new ApiException(
                'credential_unreadable',
                'The stored installation credential could not be decrypted.'
            );
        }
        return $value === '' ? null : $value;
    }

    /**
     * Encrypt the credential with Magento before writing Default-scope configuration.
     *
     * @param string $credential
     * @return void
     */
    public function save(string $credential): void
    {
        $this->writer->save(
            self::PATH,
            $this->encryptor->encrypt($credential),
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0
        );
        $this->reinitableConfig->reinit();
    }

    /**
     * Remove the saved credential and refresh the local configuration view.
     *
     * @return void
     */
    public function delete(): void
    {
        $this->writer->delete(self::PATH, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);
        $this->reinitableConfig->reinit();
    }
}
