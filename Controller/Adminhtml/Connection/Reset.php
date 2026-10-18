<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Controller\Adminhtml\Connection;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Stoneholl\Connector\Model\Api\ApiException;
use Stoneholl\Connector\Model\VerifiedLocalResetService;

class Reset extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Stoneholl_Connector::disconnect';

    /** @var VerifiedLocalResetService */
    private $resetService;

    /**
     * Initialize explicit dependencies for reset.
     *
     * @param Context $context
     * @param VerifiedLocalResetService $resetService
     */
    public function __construct(Context $context, VerifiedLocalResetService $resetService)
    {
        parent::__construct($context);
        $this->resetService = $resetService;
    }

    /**
     * Perform server-verified reset after unlink without exposing credentials.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        try {
            $this->resetService->execute();
            $this->messageManager->addSuccessMessage(
                __('Connector was reset locally. Create a new pairing code to connect it again.')
            );
        } catch (ApiException $exception) {
            $this->messageManager->addErrorMessage(__($exception->getMessage()));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(
                __('Reset after unlink could not be completed. Local Connector state was retained.')
            );
        }

        return $this->resultRedirectFactory->create()->setPath('stoneholl_connector/connection/index');
    }
}
