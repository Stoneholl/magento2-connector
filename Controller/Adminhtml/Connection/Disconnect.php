<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Controller\Adminhtml\Connection;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Stoneholl\Connector\Model\Api\ApiException;
use Stoneholl\Connector\Model\DisconnectService;

class Disconnect extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Stoneholl_Connector::disconnect';

    /** @var DisconnectService */
    private $disconnectService;

    /**
     * Initialize explicit dependencies for disconnect.
     *
     * @param Context $context
     * @param DisconnectService $disconnectService
     */
    public function __construct(Context $context, DisconnectService $disconnectService)
    {
        parent::__construct($context);
        $this->disconnectService = $disconnectService;
    }

    /**
     * Unlink remotely before clearing local connection state.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        if (!$this->getRequest()->isPost()) {
            $this->messageManager->addErrorMessage(__('Disconnect requires a POST request.'));
        } else {
            try {
                $this->disconnectService->execute();
                $this->messageManager->addSuccessMessage(
                    __('The installation was unlinked from Stoneholl and its local Connector state was removed.')
                );
            } catch (ApiException $exception) {
                $this->messageManager->addErrorMessage(__($exception->getMessage()));
            } catch (\Throwable $exception) {
                $this->messageManager->addErrorMessage(
                    __(
                        'Server unlink failed. The local credential was retained for a safe retry.'
                    )
                );
            }
        }
        return $this->resultRedirectFactory->create()->setPath('stoneholl_connector/connection/index');
    }
}
