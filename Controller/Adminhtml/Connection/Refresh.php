<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Controller\Adminhtml\Connection;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Stoneholl\Connector\Model\Api\ApiException;
use Stoneholl\Connector\Model\Synchronizer;

class Refresh extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Stoneholl_Connector::refresh';

    /** @var Synchronizer */
    private $synchronizer;

    /**
     * Initialize explicit dependencies for refresh.
     *
     * @param Context $context
     * @param Synchronizer $synchronizer
     */
    public function __construct(Context $context, Synchronizer $synchronizer)
    {
        parent::__construct($context);
        $this->synchronizer = $synchronizer;
    }

    /**
     * Refresh the snapshot or report that another connection operation is active.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        if (!$this->getRequest()->isPost()) {
            $this->messageManager->addErrorMessage(__('Refresh requires a POST request.'));
            return $this->redirect();
        }
        try {
            $completed = $this->synchronizer->execute();
            $this->messageManager->addSuccessMessage(
                $completed
                    ? __('Stoneholl status was refreshed.')
                    : __('Another Connector operation is already running. Try again after it finishes.')
            );
        } catch (ApiException $exception) {
            $this->messageManager->addErrorMessage(__($exception->getMessage()));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(
                __('Status refresh failed. Installed functionality is unaffected.')
            );
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
