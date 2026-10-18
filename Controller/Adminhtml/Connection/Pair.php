<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Controller\Adminhtml\Connection;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Stoneholl\Connector\Model\Api\ApiException;
use Stoneholl\Connector\Model\PairingService;

class Pair extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Stoneholl_Connector::pair';

    /** @var PairingService */
    private $pairingService;

    /**
     * Initialize explicit dependencies for pair.
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
     * Validate submitted pairing fields and report safe workflow diagnostics.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        if (!$this->getRequest()->isPost()) {
            $this->messageManager->addErrorMessage(__('Pairing requires a POST request.'));
            return $this->redirect();
        }
        try {
            $this->pairingService->pair(
                (string)$this->getRequest()->getParam('pairing_code'),
                (string)$this->getRequest()->getParam('environment')
            );
            $this->messageManager->addSuccessMessage(__('This Magento installation is paired with Stoneholl.'));
        } catch (ApiException $exception) {
            $this->messageManager->addErrorMessage(__($exception->getMessage()));
        } catch (\InvalidArgumentException $exception) {
            $this->messageManager->addErrorMessage(__($exception->getMessage()));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('Pairing could not be completed. No secret was logged.'));
        }
        return $this->redirect();
    }

    /**
     * Return to the Connector page without placing credentials in the URL.
     *
     * @return Redirect
     */
    private function redirect(): Redirect
    {
        return $this->resultRedirectFactory->create()->setPath('stoneholl_connector/connection/index');
    }
}
