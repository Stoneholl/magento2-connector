<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Controller\Adminhtml\Connection;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Stoneholl\Connector\Model\Api\ApiException;
use Stoneholl\Connector\Model\PairingService;

class Recover extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Stoneholl_Connector::pair';

    /** @var PairingService */
    private $pairingService;

    /**
     * Initialize explicit dependencies for recover.
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
     * Recover the exact saved installation using an account-issued code.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        try {
            $this->pairingService->recover((string)$this->getRequest()->getParam('pairing_code'));
            $this->messageManager->addSuccessMessage(__('The Stoneholl connection was recovered.'));
        } catch (ApiException $exception) {
            $this->messageManager->addErrorMessage(__($exception->getMessage()));
        } catch (\InvalidArgumentException $exception) {
            $this->messageManager->addErrorMessage(__($exception->getMessage()));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(
                __('Connection recovery could not be completed. No secret was logged.')
            );
        }

        return $this->resultRedirectFactory->create()->setPath('stoneholl_connector/connection/index');
    }
}
