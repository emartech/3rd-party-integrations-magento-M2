<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Mapping\Emrattribute;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Class Delete
 * @package Emarsys\Emarsys\Controller\Adminhtml\Mapping\Emrattribute
 */
class Delete extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \Magento\Backend\Model\Session
     */
    protected $session;

    /**
     * @var \Emarsys\Emarsys\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \Emarsys\Emarsys\Model\ResourceModel\Customer
     */
    protected $resourceModelCustomer;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $_resultJsonFactory;

    /**
     * Delete constructor.
     * @param Context $context
     * @param \Emarsys\Emarsys\Model\CustomerFactory $customerFactory
     * @param \Emarsys\Emarsys\Model\ResourceModel\Customer $resourceModelCustomer
     * @param \Emarsys\Emarsys\Helper\Data $emsrsysHelper
     * @param \Emarsys\Emarsys\Helper\Logs $logHelper
     * @param \Emarsys\Emarsys\Model\Logs $emarsysLogs
     * @param \Emarsys\Emarsys\Model\Emrattribute $Emrattribute
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
     * @param PageFactory $resultPageFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        \Emarsys\Emarsys\Model\CustomerFactory $customerFactory,
        \Emarsys\Emarsys\Model\ResourceModel\Customer $resourceModelCustomer,
        \Emarsys\Emarsys\Helper\Data $emsrsysHelper,
        \Emarsys\Emarsys\Helper\Logs $logHelper,
        \Emarsys\Emarsys\Model\Logs $emarsysLogs,
        \Emarsys\Emarsys\Model\Emrattribute $Emrattribute,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        PageFactory $resultPageFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->session = $context->getSession();
        $this->emarsysLogs = $emarsysLogs;
        $this->emsrsysHelper = $emsrsysHelper;
        $this->resultPageFactory = $resultPageFactory;
        $this->resourceModelCustomer = $resourceModelCustomer;
        $this->customerFactory = $customerFactory;
        $this->logHelper = $logHelper;
        $this->Emrattribute = $Emrattribute;
        $this->date = $date;
        $this->_storeManager = $storeManager;
        $this->_resultJsonFactory = $resultJsonFactory;
    }

    /**
     * @return $this|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $requestParams = $this->getRequest()->getParams();
        $productId = $requestParams['Id'];
        $productAttribute = $this->Emrattribute->load($productId);
        if ($productAttribute->getId()) {
            $productAttribute->delete();
        }
        $data['status'] = 'SUCCESS';
        $resultJson = $this->_resultJsonFactory->create();
        $data['status'] = 'SUCCESS';
        return $resultJson->setData($data);
    }
}
