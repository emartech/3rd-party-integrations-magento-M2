<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Mapping\Customer;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Helper\Logs as EmarsysHelperLogs;
use Emarsys\Emarsys\Model\Logs;
use Emarsys\Emarsys\Model\ResourceModel\Customer as EmarsysResourceModelCustomer;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use Zend_Json;

class SaveSchema extends Action
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
     * @var EmarsysResourceModelCustomer
     */
    protected $customerResourceModel;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var Attribute
     */
    protected $attribute;

    /**
     * @var EmarsysHelperLogs
     */
    protected $logsHelper;

    /**
     * @var Logs
     */
    protected $emarsysLogs;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * SaveSchema constructor.
     *
     * @param Context $context
     * @param EmarsysHelper $emarsysHelper
     * @param EmarsysResourceModelCustomer $customerResourceModel
     * @param PageFactory $resultPageFactory
     * @param Logs $emarsysLogs
     * @param EmarsysHelperLogs $logsHelper
     * @param DateTime $date
     * @param StoreManagerInterface $storeManager
     * @param Attribute $attribute
     */
    public function __construct(
        Context $context,
        EmarsysHelper $emarsysHelper,
        EmarsysResourceModelCustomer $customerResourceModel,
        PageFactory $resultPageFactory,
        Logs $emarsysLogs,
        EmarsysHelperLogs $logsHelper,
        DateTime $date,
        StoreManagerInterface $storeManager,
        Attribute $attribute
    ) {
        parent::__construct($context);
        $this->emarsysHelper = $emarsysHelper;
        $this->session = $context->getSession();
        $this->resultPageFactory = $resultPageFactory;
        $this->customerResourceModel = $customerResourceModel;
        $this->date = $date;
        $this->emarsysLogs = $emarsysLogs;
        $this->logsHelper = $logsHelper;
        $this->_storeManager = $storeManager;
        $this->attribute = $attribute;
    }

    /**
     * @return $this|ResponseInterface|ResultInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        /**
         * To Get the schema from Emarsys and add/update in magento mapping table
         */
        $storeId = $this->getRequest()->getParam('store');
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);
        $websiteId = $this->_storeManager->getStore($storeId)->getWebsiteId();
        $resultRedirect = $this->resultRedirectFactory->create();
        try {
            $logsArray['job_code'] = 'Customer Mapping';
            $logsArray['status'] = 'started';
            $logsArray['messages'] = 'Running Update Schema';
            $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = 'Automatic';
            $logsArray['auto_log'] = 'Complete';
            $logsArray['website_id'] = $websiteId;
            $logsArray['store_id'] = $storeId;
            $logId = $this->logsHelper->manualLogs($logsArray);

            $customerAttData = $this->attribute->getCollection()
                ->addFieldToSelect('frontend_label')
                ->addFieldToSelect('attribute_code')
                ->addFieldToSelect('entity_type_id')
                ->addFieldToFilter('entity_type_id', ['in' => '1, 2'])
                ->getData();
            $this->customerResourceModel->insertCustomerMageAtts($customerAttData, $storeId);

            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Update Schema';
            $logsArray['description'] = 'Updated Schema as ' . Zend_Json::encode($customerAttData);
            $logsArray['action'] = 'Update Schema Successful';
            $logsArray['message_type'] = 'Success';
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['log_action'] = 'True';
            $logsArray['status'] = 'success';
            $logsArray['messages'] = 'Update Schema Completed Successfully';
            $this->logsHelper->manualLogs($logsArray);
            $schemaData = $this->emarsysHelper->getEmarsysCustomerSchema($storeId);

            if (isset($schemaData['data']) && !empty($schemaData['data'])) {
                $this->customerResourceModel->updateCustomerSchema($schemaData, $storeId);
                $this->messageManager->addSuccessMessage('Customer schema added/updated successfully');
            } elseif (isset($schemaData['replyText'])) {
                $this->messageManager->addErrorMessage($schemaData['replyText']);
            } elseif (isset($schemaData['errorMessage'])) {
                $this->messageManager->addErrorMessage($schemaData['errorMessage']);
                $this->emarsysLogs->addErrorLog(
                    'Customer schema added/updated',
                    $schemaData['errorMessage'],
                    $storeId,
                    'SaveSchema(Customer)'
                );
            }
        } catch (Exception $e) {
            $this->emarsysLogs->addErrorLog(
                'Customer schema added/updated',
                $e->getMessage(),
                $storeId,
                'SaveSchema(Customer)'
            );
        }

        return $resultRedirect->setRefererOrBaseUrl();
    }
}
