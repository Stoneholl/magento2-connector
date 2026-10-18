<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Controller\Adminhtml\Connection;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Stoneholl_Connector::view';

    /** @var PageFactory */
    private $pageFactory;

    /**
     * Initialize explicit dependencies for index.
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(Context $context, PageFactory $pageFactory)
    {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
    }

    /**
     * Render the read-only Admin page under its declared ACL resource.
     *
     * @return mixed
     */
    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Stoneholl_Connector::root');
        $page->getConfig()->getTitle()->prepend(__('Stoneholl Connector'));
        return $page;
    }
}
