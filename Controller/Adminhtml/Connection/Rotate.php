<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Controller\Adminhtml\Connection;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Stoneholl\Connector\Model\Api\ApiException;
use Stoneholl\Connector\Model\CredentialRotationService;

class Rotate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Stoneholl_Connector::credentials';

    /** @var CredentialRotationService */
    private $rotationService;

    /**
     * Initialize explicit dependencies for rotate.
     *
     * @param Context $context
     * @param CredentialRotationService $rotationService
     */
    public function __construct(Context $context, CredentialRotationService $rotationService)
    {
        parent::__construct($context);
        $this->rotationService = $rotationService;
    }

    /**
     * Resume credential rotation and report safe diagnostics without asserting server rollback.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        try {
            $this->rotationService->execute();
            $this->messageManager->addSuccessMessage(__('The installation credential was rotated successfully.'));
        } catch (ApiException $exception) {
            $this->messageManager->addErrorMessage(__($exception->getMessage()));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(
                __('Credential rotation did not complete. Retry to resume the saved operation. No secret was logged.')
            );
        }
        return $this->resultRedirectFactory->create()->setPath('stoneholl_connector/connection/index');
    }
}
