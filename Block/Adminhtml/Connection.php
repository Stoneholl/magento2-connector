<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Data\Form\FormKey;
use Stoneholl\Connector\Model\Config;
use Stoneholl\Connector\Model\ConnectionStatus;
use Stoneholl\Connector\Model\CredentialStorage;
use Stoneholl\Connector\Model\Api\ApiException;
use Stoneholl\Connector\Model\InstallationDomain;
use Stoneholl\Connector\Model\Platform;
use Stoneholl\Connector\Model\StateRepository;
use Stoneholl\Connector\Model\StatusResolver;
use Stoneholl\Connector\Model\SecureWorkflowStorage;
use Stoneholl\Connector\Model\VerifiedLocalResetAvailability;

class Connection extends Template
{
    /** @var StateRepository */
    private $stateRepository;
    /** @var StatusResolver */
    private $statusResolver;
    /** @var CredentialStorage */
    private $credentialStorage;
    /** @var Platform */
    private $platform;
    /** @var InstallationDomain */
    private $installationDomain;
    /** @var Config */
    private $config;
    /** @var FormKey */
    private $formKeyService;
    /** @var AuthorizationInterface */
    private $authorization;
    /** @var SecureWorkflowStorage */
    private $workflowStorage;

    /**
     * Initialize explicit dependencies for connection.
     *
     * @param Context $context
     * @param StateRepository $stateRepository
     * @param StatusResolver $statusResolver
     * @param CredentialStorage $credentialStorage
     * @param Platform $platform
     * @param InstallationDomain $installationDomain
     * @param Config $config
     * @param FormKey $formKey
     * @param AuthorizationInterface $authorization
     * @param SecureWorkflowStorage $workflowStorage
     * @param array $data
     */
    public function __construct(
        Context $context,
        StateRepository $stateRepository,
        StatusResolver $statusResolver,
        CredentialStorage $credentialStorage,
        Platform $platform,
        InstallationDomain $installationDomain,
        Config $config,
        FormKey $formKey,
        AuthorizationInterface $authorization,
        SecureWorkflowStorage $workflowStorage,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->stateRepository = $stateRepository;
        $this->statusResolver = $statusResolver;
        $this->credentialStorage = $credentialStorage;
        $this->platform = $platform;
        $this->installationDomain = $installationDomain;
        $this->config = $config;
        $this->formKeyService = $formKey;
        $this->authorization = $authorization;
        $this->workflowStorage = $workflowStorage;
    }

    /**
     * Load the current persisted connection state for this page.
     *
     * @return \Stoneholl\Connector\Model\State
     */
    public function getState()
    {
        return $this->stateRepository->get();
    }

    /**
     * Resolve the displayed state, accounting for missing credentials and stale cache.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->statusResolver->resolve($this->getState());
    }

    /**
     * Translate the resolved connection state into its Admin label.
     *
     * @return string
     */
    public function getStatusLabel(): string
    {
        $labels = [
            ConnectionStatus::UNPAIRED => (string)__('Not Paired'),
            ConnectionStatus::PAIRING_CONFIRMATION_PENDING => (string)__('Pairing Confirmation Pending'),
            ConnectionStatus::CONNECTED => (string)__('Connected'),
            ConnectionStatus::OFFLINE => (string)__('Offline'),
            ConnectionStatus::STALE => (string)__('Stale'),
            ConnectionStatus::CREDENTIAL_REJECTED => (string)__('Credential Rejected'),
        ];
        return $labels[$this->getStatus()] ?? (string)__('Unknown');
    }

    /**
     * Treat a saved or unreadable credential as paired to prevent unsafe new-installation pairing.
     *
     * @return bool
     */
    public function isPaired(): bool
    {
        try {
            return $this->credentialStorage->has();
        } catch (ApiException $exception) {
            return true;
        }
    }

    /**
     * Read the last complete normalized snapshot without issuing network requests.
     *
     * @return array
     */
    public function getSnapshot(): array
    {
        return $this->stateRepository->getSnapshot($this->getState());
    }

    /**
     * Expose the technical platform dimensions disclosed before pairing.
     *
     * @return array
     */
    public function getPlatform(): array
    {
        return $this->platform->getData();
    }

    /**
     * Read the normalized default-store host disclosed before pairing.
     *
     * @return string
     */
    public function getInstallationDomain(): string
    {
        return $this->installationDomain->get();
    }

    /**
     * Expose the configured API destination for disclosure in Admin.
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->config->getApiUrl();
    }

    /**
     * Return the validated navigation-only Account URL or an empty link for invalid configuration.
     *
     * @return string
     */
    public function getAccountUrl(): string
    {
        return $this->config->getAccountUrl();
    }

    /**
     * Supply Magento CSRF protection for connection action forms.
     *
     * @return string
     */
    public function getFormKey(): string
    {
        return $this->formKeyService->getFormKey();
    }

    /**
     * Check the pairing ACL independently of status visibility.
     *
     * @return bool
     */
    public function canPair(): bool
    {
        return $this->authorization->isAllowed('Stoneholl_Connector::pair');
    }

    /**
     * Check the manual-refresh ACL independently of status visibility.
     *
     * @return bool
     */
    public function canRefresh(): bool
    {
        return $this->authorization->isAllowed('Stoneholl_Connector::refresh');
    }

    /**
     * Check the credential-management ACL independently of status visibility.
     *
     * @return bool
     */
    public function canManageCredentials(): bool
    {
        return $this->authorization->isAllowed('Stoneholl_Connector::credentials');
    }

    /**
     * Check the disconnect ACL independently of status visibility.
     *
     * @return bool
     */
    public function canDisconnect(): bool
    {
        return $this->authorization->isAllowed('Stoneholl_Connector::disconnect');
    }

    /**
     * Report whether an encrypted pairing attempt awaits completion.
     *
     * @return bool
     */
    public function hasPendingPairing(): bool
    {
        try {
            return $this->workflowStorage->getPairing() !== null;
        } catch (ApiException $exception) {
            return true;
        }
    }

    /**
     * Build the pair action URL using Magento Admin routing.
     *
     * @return string
     */
    public function getPairUrl(): string
    {
        return $this->getUrl('stoneholl_connector/connection/pair');
    }

    /**
     * Build the recover action URL using Magento Admin routing.
     *
     * @return string
     */
    public function getRecoverUrl(): string
    {
        return $this->getUrl('stoneholl_connector/connection/recover');
    }

    /**
     * Build the refresh action URL using Magento Admin routing.
     *
     * @return string
     */
    public function getRefreshUrl(): string
    {
        return $this->getUrl('stoneholl_connector/connection/refresh');
    }

    /**
     * Build the confirm action URL using Magento Admin routing.
     *
     * @return string
     */
    public function getConfirmUrl(): string
    {
        return $this->getUrl('stoneholl_connector/connection/confirm');
    }

    /**
     * Build the rotate action URL using Magento Admin routing.
     *
     * @return string
     */
    public function getRotateUrl(): string
    {
        return $this->getUrl('stoneholl_connector/connection/rotate');
    }

    /**
     * Build the disconnect action URL using Magento Admin routing.
     *
     * @return string
     */
    public function getDisconnectUrl(): string
    {
        return $this->getUrl('stoneholl_connector/connection/disconnect');
    }

    /**
     * Build the forget action URL using Magento Admin routing.
     *
     * @return string
     */
    public function getForgetUrl(): string
    {
        return $this->getUrl('stoneholl_connector/connection/forget');
    }

    /**
     * Build the reset after unlink action URL using Magento Admin routing.
     *
     * @return string
     */
    public function getResetAfterUnlinkUrl(): string
    {
        return $this->getUrl('stoneholl_connector/connection/reset');
    }

    /**
     * Require disconnect permission and verified-reset availability before showing the action.
     *
     * @return bool
     */
    public function canResetAfterUnlink(): bool
    {
        $availability = $this->getData('verified_local_reset_availability');
        return $this->canDisconnect()
            && $availability instanceof VerifiedLocalResetAvailability
            && $availability->isAvailable();
    }

    /**
     * Build the configuration link only for roles permitted to edit Connector settings.
     *
     * @return string
     */
    public function getConfigUrl(): string
    {
        if (!$this->authorization->isAllowed('Stoneholl_Connector::config')) {
            return '';
        }
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'stoneholl_connector']);
    }
}
