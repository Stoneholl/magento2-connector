<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Controller\Adminhtml\Connection;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Stoneholl\Connector\Model\PendingPairingForgetService;

class Forget extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Stoneholl_Connector::disconnect';

    /** @var PendingPairingForgetService */
    private $localForgetService;

    /**
     * Initialize explicit dependencies for forget.
     *
     * @param Context $context
     * @param PendingPairingForgetService $localForgetService
     */
    public function __construct(Context $context, PendingPairingForgetService $localForgetService)
    {
        parent::__construct($context);
        $this->localForgetService = $localForgetService;
    }

    /**
     * Discard only an unfinished unpaired draft permitted by the cleanup service.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        try {
            $this->localForgetService->execute();
            $this->messageManager->addWarningMessage(
                __('Local Connector state was removed. Any server-side installation credential remains active.')
            );
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('The local Connector state could not be removed.'));
        }
        return $this->resultRedirectFactory->create()->setPath('stoneholl_connector/connection/index');
    }
}
