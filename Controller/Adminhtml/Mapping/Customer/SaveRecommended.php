<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Mapping\Customer;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Helper\Logs as EmarsysHelperLogs;
use Emarsys\Emarsys\Model\CustomerFactory;
use Emarsys\Emarsys\Model\Logs;
use Emarsys\Emarsys\Model\ResourceModel\Customer;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use Zend_Json;

class SaveRecommended extends Action
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
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var Customer
     */
    protected $resourceModelCustomer;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var EmarsysHelperLogs
     */
    protected $logsHelper;

    /**
     * @var Logs
     */
    protected $emarsysLogs;

    /**
     * SaveRecommended constructor.
     *
     * @param Context $context
     * @param EmarsysHelper $emarsysHelper
     * @param CustomerFactory $customerFactory
     * @param Customer $resourceModelCustomer
     * @param PageFactory $resultPageFactory
     * @param Logs $emarsysLogs
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param DateTime $date
     * @param EmarsysHelperLogs $logsHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        EmarsysHelper $emarsysHelper,
        CustomerFactory $customerFactory,
        Customer $resourceModelCustomer,
        PageFactory $resultPageFactory,
        Logs $emarsysLogs,
        ScopeConfigInterface $scopeConfigInterface,
        DateTime $date,
        EmarsysHelperLogs $logsHelper,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->emarsysHelper = $emarsysHelper;
        $this->session = $context->getSession();
        $this->resultPageFactory = $resultPageFactory;
        $this->customerFactory = $customerFactory;
        $this->resourceModelCustomer = $resourceModelCustomer;
        $this->date = $date;
        $this->emarsysLogs = $emarsysLogs;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->logsHelper = $logsHelper;
        $this->_storeManager = $storeManager;
    }

    /**
     * @return Redirect
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $storeId = $this->getRequest()->getParam('store');
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);
        $websiteId = $this->_storeManager->getStore($storeId)->getWebsiteId();
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

            //Collect custom customer attribute mapping
            $customerCustomMappedAttrs = $this->resourceModelCustomer->getCustomMappedCustomerAttribute($storeId);

            // Truncating the Mapping Table first
            $this->resourceModelCustomer->truncateMappingTable($storeId);

            //Add custom customer attribute mapping
            if (!empty($customerCustomMappedAttrs)) {
                foreach ($customerCustomMappedAttrs as $key => $value) {
                    $model = $this->customerFactory->create();
                    $model->setEmarsysContactField($value['emarsys_contact_field'])
                        ->setMagentoAttributeId($value['magento_attribute_id'])
                        ->setMagentoCustomAttributeId($value['magento_custom_attribute_id'])
                        ->setStoreId($storeId)
                        ->save();
                }
            }

            // Here We need set the recommended attribute values
            $recommendedData = $this->resourceModelCustomer->getRecommendedCustomerAttribute($storeId);
            $emarsysCodes = [
                'first_name' => 'firstname',
                'middle_name' => 'middlename',
                'last_name' => 'lastname',
                'email' => 'email',
                'gender' => 'gender',
                'birth_date' => 'dob',
            ];
            if (isset($recommendedData['magento'])) {
                foreach ($recommendedData['magento'] as $key => $code) {
                    if (isset($recommendedData['emarsys'][$key]) && !empty($recommendedData['emarsys'][$key])) {
                        $custMageId = $this->resourceModelCustomer->getCustAttIdByCode($emarsysCodes[$key], $storeId);
                        $model = $this->customerFactory->create();
                        $model->setEmarsysContactField($recommendedData['emarsys'][$key])
                            ->setMagentoAttributeId($code)
                            ->setMagentoCustomAttributeId($custMageId)
                            ->setStoreId($storeId)
                            ->save();
                    }
                }
                $logsArray['id'] = $logId;
                $logsArray['emarsys_info'] = 'Recommended Mapping';
                $logsArray['description'] = 'Saved Recommended Mapping as ' . Zend_Json::encode($emarsysCodes);
                $logsArray['action'] = 'Update Schema Successful';
                $logsArray['message_type'] = 'Success';
                $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['log_action'] = 'True';
                $logsArray['status'] = 'success';
                $logsArray['messages'] = 'Update Schema Completed Successfully';
                $this->logsHelper->manualLogs($logsArray);
                $this->messageManager->addSuccessMessage(__("Recommended Customer attributes mapped successfully"));
            } else {
                $this->messageManager->addErrorMessage(__("No Recommendations are added"));
            }
        } catch (Exception $e) {
            $this->emarsysLogs->addErrorLog(
                'Running Customer Recommended Mapping',
                $e->getMessage(),
                $storeId,
                'SaveSchema(Customer)'
            );
            $this->messageManager->addErrorMessage(__("Error occurred while mapping Customer attribute"));
        }
        $resultRedirect = $this->resultRedirectFactory->create();

        return $resultRedirect->setRefererOrBaseUrl();
    }
}
