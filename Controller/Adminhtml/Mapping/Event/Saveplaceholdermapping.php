<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Mapping\Event;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\View\Result\PageFactory;
use Emarsys\Emarsys\Model\EventFactory;
use Emarsys\Emarsys\Model\ResourceModel\Event;
use Emarsys\Emarsys\Model\PlaceholdersFactory;
use Zend_Json;

class Saveplaceholdermapping extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var EventFactory
     */
    protected $eventFactory;

    /**
     * Saveplaceholdermapping constructor.
     *
     * @param Context $context
     * @param EventFactory $eventFactory
     * @param Event $eventResourceModel
     * @param PlaceholdersFactory $emarsysEventPlaceholderMappingFactory
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        EventFactory $eventFactory,
        Event $eventResourceModel,
        PlaceholdersFactory $emarsysEventPlaceholderMappingFactory,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->session = $context->getSession();
        $this->eventResourceModel = $eventResourceModel;
        $this->resultPageFactory = $resultPageFactory;
        $this->eventFactory = $eventFactory;
        $this->emarsysEventPlaceholderMappingFactory = $emarsysEventPlaceholderMappingFactory;
    }

    /**
     * SavePlaceHolderMapping Action
     *
     * @return $this
     */
    public function execute()
    {
        try {
            $resultRedirect = $this->resultRedirectFactory->create();
            $placeholderData = $this->getRequest()->getPost('placeholderData');
            $placeholders = html_entity_decode($placeholderData);
            $placeholderDataDecode = Zend_Json::decode($placeholders, true);

            foreach ($placeholderDataDecode as $placeholderItem) {
                foreach ($placeholderItem as $key => $value) {
                    $emarsysEventPlaceholderMapping = $this->emarsysEventPlaceholderMappingFactory->create()
                        ->load($key);
                    $emarsysEventPlaceholderMapping->setEmarsysPlaceholderName($value)
                        ->save();
                }
            }
            $this->messageManager->addSuccessMessage(__('Placeholders mapped successfully'));
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('Error occurred while mapping Event %1', $e->getMessage()));
        }

        return $resultRedirect->setRefererOrBaseUrl();
    }
}
