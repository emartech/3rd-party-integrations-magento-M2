<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Mapping\Field;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Model\FieldFactory;
use Emarsys\Emarsys\Model\ResourceModel\Field;
use Emarsys\Emarsys\Helper\Logs;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Emarsys\Emarsys\Model\Logs as EmarsysModelLogs;
use Magento\Framework\App\Config\ScopeConfigInterface;
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
     * @var DateTime
     */
    protected $date;

    /**
     * @var FieldFactory
     */
    protected $fieldFactory;

    /**
     * @var Field
     */
    protected $resourceModelField;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var EmarsysModelLogs
     */
    protected $emarsysLogs;

    /**
     * @var Logs
     */
    protected $logsHelper;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * SaveRecommended constructor.
     *
     * @param Context $context
     * @param FieldFactory $fieldFactory
     * @param Field $resourceModelField
     * @param PageFactory $resultPageFactory
     * @param Logs $logsHelper
     * @param DateTime $date
     * @param EmarsysModelLogs $emarsysLogs
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        EmarsysHelper $emarsysHelper,
        FieldFactory $fieldFactory,
        Field $resourceModelField,
        PageFactory $resultPageFactory,
        Logs $logsHelper,
        DateTime $date,
        EmarsysModelLogs $emarsysLogs,
        ScopeConfigInterface $scopeConfigInterface,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->emarsysHelper = $emarsysHelper;
        $this->session = $context->getSession();
        $this->resultPageFactory = $resultPageFactory;
        $this->date = $date;
        $this->emarsysLogs = $emarsysLogs;
        $this->fieldFactory = $fieldFactory;
        $this->resourceModelField = $resourceModelField;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->_storeManager = $storeManager;
        $this->logsHelper = $logsHelper;
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
        $resultRedirect = $this->resultRedirectFactory->create();
        try {
            $logsArray['job_code'] = 'Customer Filed Mapping';
            $logsArray['status'] = 'started';
            $logsArray['messages'] = 'Running Recommended Mapping';
            $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = 'Automatic';
            $logsArray['auto_log'] = 'Complete';
            $logsArray['website_id'] = $websiteId;
            $logsArray['store_id'] = $storeId;
            $this->resourceModelField->truncateMappingTable($storeId);
            $recommendedData = $this->resourceModelField->getRecommendedFieldAttribute($storeId);
            if (count($recommendedData)) {
                foreach ($recommendedData as $key => $code) {
                    if (isset($recommendedData)) {
                        $value = explode('-', $code);
                        $model = $this->fieldFactory->create();
                        $model->setEmarsysOptionId($value[0]);
                        $model->setEmarsysFieldId($value[1]);
                        $model->setMagentoOptionId($key);
                        $model->setStoreId($storeId);
                        $model->save();
                    }
                }
                $logId = $this->logsHelper->manualLogs($logsArray);
                $logsArray['id'] = $logId;
                $logsArray['emarsys_info'] = 'Recommended Mapping';
                $logsArray['description'] = 'Saved Recommended Mapping as ' . Zend_Json::encode($recommendedData);
                $logsArray['action'] = 'Saved Recommended Mapping Successful';
                $logsArray['message_type'] = 'Success';
                $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['log_action'] = 'True';
                $logsArray['status'] = 'success';
                $logsArray['messages'] = 'Saved Recommended Mapping Successful';
                $this->logsHelper->manualLogs($logsArray);
                $this->messageManager->addSuccessMessage(
                    __(
                        'Recommended Customer-Field attributes mapped successfully'
                    )
                );
            } else {
                $logId = $this->logsHelper->manualLogs($logsArray);
                $logsArray['id'] = $logId;
                $logsArray['emarsys_info'] = 'Recommended Mapping';
                $logsArray['description'] = 'No Recommended Mappings';
                $logsArray['action'] = 'Recommended Mapping Completed';
                $logsArray['message_type'] = 'Error';
                $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['log_action'] = 'True';
                $logsArray['status'] = 'error';
                $logsArray['messages'] = 'Saved Recommended Mapping Completed';
                $this->logsHelper->manualLogs($logsArray);
                $this->messageManager->addErrorMessage(__('No Recommendations are added'));
            }
        } catch (Exception $e) {
            $this->emarsysLogs->addErrorLog(
                'Running Customer Filed Recommended Mapping',
                $e->getMessage(),
                $storeId,
                'SaveRecommended (Customer Filed)'
            );
            $this->messageManager->addErrorMessage(__('Error occurred while mapping Customer-Field'));
        }

        return $resultRedirect->setRefererOrBaseUrl();
    }
}
