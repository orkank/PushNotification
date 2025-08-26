<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Controller\Adminhtml\Logs;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'IDangerous_PushNotification::manage_tokens';

    private PageFactory $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('IDangerous_PushNotification::push_notification');
        $resultPage->getConfig()->getTitle()->prepend(__('Notification Logs'));

        return $resultPage;
    }
}
