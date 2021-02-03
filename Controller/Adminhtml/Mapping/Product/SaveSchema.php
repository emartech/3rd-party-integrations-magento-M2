<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Mapping\Product;

use Emarsys\Emarsys\Helper\Data;
use Emarsys\Emarsys\Model\Logs;
use Emarsys\Emarsys\Model\ResourceModel\Product;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
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
     * @var Product
     */
    protected $productResourceModel;

    /**
     * @param Context $context
     * @param Product $productResourceModel
     * @param Logs $emarsysLogs
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        Product $productResourceModel,
        Data $emarsysHelper,
        StoreManagerInterface $storeManager,
        DateTime $date,
        \Emarsys\Emarsys\Helper\Logs $logsHelper,
        Logs $emarsysLogs,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->session = $context->getSession();
        $this->resultPageFactory = $resultPageFactory;
        $this->emarsysHelper = $emarsysHelper;
        $this->date = $date;
        $this->_storeManager = $storeManager;
        $this->emarsysLogs = $emarsysLogs;
        $this->logsHelper = $logsHelper;
        $this->productResourceModel = $productResourceModel;
    }

    public function execute()
    {
        $storeId = $this->getRequest()->getParam('store', false);
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);
        $websiteId = $this->_storeManager->getStore($storeId)->getWebsiteId();
        try {
            $productFields = [];
            $logsArray['job_code'] = 'Product Mapping';
            $logsArray['status'] = 'started';
            $logsArray['messages'] = 'Running Update Schema';
            $logsArray['description'] = 'Started Updating Product Mapping';
            $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = 'Automatic';
            $logsArray['auto_log'] = 'Complete';
            $logsArray['website_id'] = $websiteId;
            $logsArray['store_id'] = $storeId;
            $logId = $this->logsHelper->manualLogs($logsArray);

            $productFields = $this->productResourceModel->updateProductSchema($storeId);

            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Update Product Mapping';
            $logsArray['action'] = 'Update Product Mapping Successful';
            $logsArray['message_type'] = 'Success';
            $logsArray['description'] = 'Product Mapping Updated as ' . Zend_Json::encode($productFields);
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['log_action'] = 'True';
            $logsArray['status'] = 'success';
            $logsArray['messages'] = 'Update Product Mapping Saved Successfully';
            $this->logsHelper->manualLogs($logsArray);
            $this->messageManager->addSuccessMessage(__('Product schema added/updated successfully'));
        } catch (Exception $e) {
            if ($logId) {
                $logsArray['id'] = $logId;
                $logsArray['emarsys_info'] = 'Update Product Mapping not Successful';
                $logsArray['description'] = $e->getMessage();
                $logsArray['action'] = 'Update Product Mapping Failed';
                $logsArray['message_type'] = 'Error';
                $logsArray['log_action'] = 'Update Product Mapping';
                $logsArray['messages'] = 'Update Product Mapping not Successful';
                $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                $this->logsHelper->manualLogs($logsArray);
            }
            $this->messageManager->addErrorMessage(
                __(
                    'There was a problem while Updating Product Mapping. Please refer emarsys logs for more information.'
                )
            );
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setRefererOrBaseUrl();
    }
}
