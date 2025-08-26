<?php
declare(strict_types=1);

namespace IDangerous\PushNotification\Controller\Adminhtml\Tokens;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use IDangerous\PushNotification\Model\TokenFactory;
use Magento\Framework\Controller\ResultFactory;

class Delete extends Action
{
    private TokenFactory $tokenFactory;

    public function __construct(
        Context $context,
        TokenFactory $tokenFactory
    ) {
        $this->tokenFactory = $tokenFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $id = $this->getRequest()->getParam('id');

        if ($id) {
            try {
                $token = $this->tokenFactory->create()->load($id);
                if ($token->getId()) {
                    $token->delete();
                    $this->messageManager->addSuccessMessage(__('The token has been deleted.'));
                } else {
                    $this->messageManager->addErrorMessage(__('Token not found.'));
                }
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('An error occurred while deleting the token.'));
            }
        }

        return $resultRedirect->setPath('*/*/');
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('IDangerous_PushNotification::tokens');
    }
}
