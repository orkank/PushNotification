<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Controller\Adminhtml\Tokens;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
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
        $resultPage->setActiveMenu('IDangerous_PushNotification::notification_tokens');
        $resultPage->getConfig()->getTitle()->prepend(__('Notification Tokens'));

        return $resultPage;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('IDangerous_PushNotification::tokens');
    }
}

