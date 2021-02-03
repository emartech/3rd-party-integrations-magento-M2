<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Mapping\Event;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Emarsys\Emarsys\Model\EventFactory;
use Emarsys\Emarsys\Model\ResourceModel\Event;
use Emarsys\Emarsys\Model\EmarsyseventmappingFactory;

class ChangeValue extends Action
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
     * ChangeValue constructor.
     *
     * @param Context $context
     * @param EventFactory $eventFactory
     * @param Event $eventResourceModel
     * @param EmarsyseventmappingFactory $EmarsyseventmappingFactory
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        EventFactory $eventFactory,
        Event $eventResourceModel,
        EmarsyseventmappingFactory $EmarsyseventmappingFactory,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->session = $context->getSession();
        $this->eventResourceModel = $eventResourceModel;
        $this->EmarsyseventmappingFactory = $EmarsyseventmappingFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->eventFactory = $eventFactory;
        $this->_urlInterface = $context->getUrl();
    }

    /**
     * @return $this|ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $magentoEventId = $this->getRequest()->getParam('magentoeventId');
        $emarsysEventId = $this->getRequest()->getParam('emarsyseventId');
        $id = $this->getRequest()->getParam('Id');
        $gridSession = $this->session->getMappingGridData();
        $gridSession[$id]['magento_event_id'] = $magentoEventId;
        $gridSession[$id]['emarsys_event_id'] = $emarsysEventId;
        $this->session->setMappingGridData($gridSession);
    }
}
