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

class Save extends \Magento\Framework\App\Action\Action
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
     * @param Context $context
     * @param \Emarsys\Emarsys\Model\CustomerFactory $customerFactory
     * @param \Emarsys\Emarsys\Model\ResourceModel\Customer $resourceModelCustomer
     * @param PageFactory $resultPageFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        \Emarsys\Emarsys\Model\CustomerFactory $customerFactory,
        \Emarsys\Emarsys\Model\ResourceModel\Customer $resourceModelCustomer,
        \Emarsys\Emarsys\Helper\Data $emsrsysHelper,
        \Emarsys\Emarsys\Helper\Logs $logHelper,
        \Emarsys\Emarsys\Model\Logs $emarsysLogs,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        PageFactory $resultPageFactory,
        \Emarsys\Emarsys\Model\EmrattributeFactory $EmrattributeFactory,
        \Emarsys\Emarsys\Model\ResourceModel\Emrattribute\CollectionFactory $EmrattributeCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->session = $context->getSession();
        $this->emarsysLogs = $emarsysLogs;
        $this->emsrsysHelper = $emsrsysHelper;
        $this->EmrattributeFactory = $EmrattributeFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->resourceModelCustomer = $resourceModelCustomer;
        $this->customerFactory = $customerFactory;
        $this->logHelper = $logHelper;
        $this->date = $date;
        $this->EmrattributeCollectionFactory = $EmrattributeCollectionFactory;
        $this->_storeManager = $storeManager;
    }

    /**
     * @return $this|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $requestParams = $this->getRequest()->getParams();
        $storeId = $this->getRequest()->getParam('store');
        for ($loop = 0; $loop < count($requestParams['field_name']); $loop++) {
            if ($requestParams['field_name'][$loop] != '') {
                $field_name = 'c_' . $requestParams['field_name'][$loop];
                $field_label = 'c_' . $requestParams['field_label'][$loop];
                $attributeCollection = $this->EmrattributeCollectionFactory->create();
                $duplicateCode = $attributeCollection->addFieldToFilter('code',array('eq'=> $field_name))->addFieldToFilter('store_id',array('eq'=>$storeId))->getFirstItem()->getCode();
                $duplicateLabel = $attributeCollection->addFieldToFilter('label',array('eq'=> $field_label))->addFieldToFilter('store_id',array('eq'=>$storeId))->getFirstItem()->getLabel();
                if($duplicateCode || $duplicateLabel){
                $this->messageManager->addErrorMessage('Attribute with Code ' . $duplicateCode . ' and Label ' . $duplicateLabel .' has not been Created due to duplication');
                    continue;
                }
                $attribute_type = $requestParams['attribute_type'][$loop];
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
                $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
                $connection = $resource->getConnection();
                $tableName = $resource->getTableName('emarsys_emarsys_product_attributes');
                $model = $this->EmrattributeFactory->create();
                $model->setCode($field_name);
                $model->setLabel($field_label);
                $attribute_type = ucfirst($attribute_type);
                $model->setFieldType($attribute_type);
                $model->setStoreId($storeId);
                $model->save();
            }

        }
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('emarsys_emarsys/mapping_emrattribute', array('store' => $storeId));
        return $resultRedirect;
    }

}
