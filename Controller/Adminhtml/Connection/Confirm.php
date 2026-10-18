<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Controller\Adminhtml\Connection;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Stoneholl\Connector\Model\Api\ApiException;
use Stoneholl\Connector\Model\PairingService;

class Confirm extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Stoneholl_Connector::pair';

    /** @var PairingService */
    private $pairingService;

    /**
     * Initialize explicit dependencies for confirm.
     *
     * @param Context $context
     * @param PairingService $pairingService
     */
    public function __construct(Context $context, PairingService $pairingService)
    {
        parent::__construct($context);
        $this->pairingService = $pairingService;
    }

    /**
     * Retry confirmation using the saved credential and attempt.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        try {
            $this->pairingService->confirmPending();
            $this->messageManager->addSuccessMessage(__('The recoverable pairing was confirmed.'));
        } catch (ApiException $exception) {
            $this->messageManager->addErrorMessage(__($exception->getMessage()));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(
                __('Pairing confirmation failed. The credential and attempt were retained for a safe retry.')
            );
        }
        return $this->resultRedirectFactory->create()->setPath('stoneholl_connector/connection/index');
    }
}
