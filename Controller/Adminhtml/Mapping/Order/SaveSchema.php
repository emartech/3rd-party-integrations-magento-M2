<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Mapping\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Emarsys\Emarsys\Helper\Logs;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Emarsys\Emarsys\Model\ResourceModel\Order;
use Magento\Store\Model\StoreManagerInterface;
use Emarsys\Emarsys\Helper\Data;

/**
 * Class SaveSchema
 * @package Emarsys\Emarsys\Controller\Adminhtml\Mapping\Order
 */
class SaveSchema extends Action
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
     * @var Order
     */
    protected $orderResourceModel;

    /**
     * SaveSchema constructor.
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Logs $logsHelper
     * @param DateTime $date
     * @param Order $orderResourceModel
     * @param StoreManagerInterface $storeManager
     * @param Data $emarsysHelper
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Logs $logsHelper,
        DateTime $date,
        Order $orderResourceModel,
        StoreManagerInterface $storeManager,
        Data $emarsysHelper
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->logsHelper = $logsHelper;
        $this->orderResourceModel = $orderResourceModel;
        $this->session = $context->getSession();
        $this->date = $date;
        $this->storeManager = $storeManager;
        $this->emarsysHelper = $emarsysHelper;
    }

    /**
     * Save action
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        try {
            $session = $this->session->getData();
            if (isset($session['store'])) {
                $storeId = $session['store'];
            } else {
                $storeId = $this->emarsysHelper->getFirstStoreId();
            }
            $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
            $logsArray['job_code'] = 'Order Mapping';
            $logsArray['status'] = 'started';
            $logsArray['messages'] = 'Running Update Schema';
            $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = 'Automatic';
            $logsArray['auto_log'] = 'Complete';
            $logsArray['store_id'] = $storeId;
            $logsArray['website_id'] = $websiteId;
            $logId = $this->logsHelper->manualLogs($logsArray);
            $data = $this->orderResourceModel->getSalesOrderColumnNames();
            $manData['order'] = 'order';
            $manData['date'] = 'date';
            $manData['customer'] = 'customer';
            $manData['item'] = 'item';
            $manData['quantity'] = 'quantity';
            $manData['unit_price'] = 'unit_price';
            $manData['c_sales_amount'] = 'c_sales_amount';
            $this->orderResourceModel->insertIntoMappingTableStaticData($manData, $storeId);
            $this->orderResourceModel->insertIntoMappingTable($data, $storeId);
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Update Schema Successful';
            $logsArray['description'] = 'Inserted Entries '.print_r($data,true);
            $logsArray['action'] = 'Update Order Schema';
            $logsArray['message_type'] = 'Success';
            $logsArray['log_action'] = 'Update Order Schema';
            $logsArray['messages'] = 'Update Schema Successful';
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
            $this->logsHelper->logs($logsArray);
            $this->logsHelper->manualLogsUpdate($logsArray);
            $this->messageManager->addSuccessMessage('Order Schema Updated Successfully.');
        } catch (\Exception $e) {
            if ($logId) {
                $logsArray['id'] = $logId;
                $logsArray['emarsys_info'] = 'Update Schema not Successful';
                $logsArray['description'] = $e->getMessage();
                $logsArray['action'] = 'Update Order Schema';
                $logsArray['message_type'] = 'Error';
                $logsArray['log_action'] = 'Update Order Schema';
                $logsArray['messages'] = 'Update Schema not Successful';
                $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                $this->logsHelper->logs($logsArray);
                $this->logsHelper->manualLogsUpdate($logsArray);
            }
            $this->messageManager->addErrorMessage(
                __('There was a problem while updating order schema. Please refer emarsys logs for more information.')
            );
        }
        $resultRedirect = $this->resultRedirectFactory->create();

        return $resultRedirect->setRefererOrBaseUrl();
    }
}
