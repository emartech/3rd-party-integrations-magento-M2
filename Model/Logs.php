<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Log
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model;

class Logs extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('Emarsys\Emarsys\Model\ResourceModel\Logs');
    }

    public function  addErrorLog($messages, $storeId, $info)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        try {
            $storeManagerInterfaceObj = $objectManager->create('\Magento\Store\Model\StoreManagerInterface');
            $date = $objectManager->create('\Magento\Framework\Stdlib\DateTime\DateTime');
            $logsArray['job_code'] = 'Exception';
            $logsArray['status'] = 'error';
            $logsArray['messages'] = $messages;
            $logsArray['created_at'] = $date->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $date->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = '';
            $logsArray['auto_log'] = '';
            $logsArray['store_id'] = $storeId;
            $logsHelper = $objectManager->create('Emarsys\Emarsys\Helper\Logs');
            $logId = $logsHelper->manualLogs($logsArray);
            if($logId){
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = $info;
            $logsArray['description'] = $messages;
            $logsArray['action'] = '';
            $logsArray['message_type'] = 'error';
            $logsArray['log_action'] = 'fail';
            $logsArray['website_id'] = $storeManagerInterfaceObj->getStore($storeId)->getWebsiteId();
            $logsHelper->logs($logsArray);
            }
        } catch (\Exception $e) {
            $messageManager = $objectManager->create('\Magento\Framework\Message\ManagerInterface');
            $messageManager->addErrorMessage(
                __('Unable to Log: ' . $e->getMessage())
            );
        }
    }
}
