<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Emarsys\Emarsys\Helper\Logs as EmarsysLogs;

/**
 * Class Logs
 */
class Logs extends \Magento\Framework\Model\AbstractModel
{
    /**
     * @var EmarsysLogs
     */
    protected $emarsysLog;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var ManagerInterface
     */
    protected $messageManagerInterface;

    /**
     * Logs constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param StoreManagerInterface $storeManager
     * @param ManagerInterface $managerInterface
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        StoreManagerInterface $storeManager,
        ManagerInterface $managerInterface,
        DateTime $dateTime,
        EmarsysLogs $emarsysLog,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->emarsysLog = $emarsysLog;
        $this->storeManager = $storeManager;
        $this->messageManagerInterface = $managerInterface;
        $this->dateTime = $dateTime;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init(\Emarsys\Emarsys\Model\ResourceModel\Logs::class);
    }

    /**
     * @param $messages
     * @param $description
     * @param $storeId
     * @param $info
     */
    public function addErrorLog($messages = '', $description = '', $storeId = 0, $info = '')
    {
        try {
            $logsArray['job_code'] = 'Exception';
            $logsArray['status'] = 'error';
            $logsArray['messages'] = $messages;
            $logsArray['created_at'] = $this->dateTime->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->dateTime->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = '';
            $logsArray['auto_log'] = '';
            $logsArray['store_id'] = $storeId;
            $logsArray['emarsys_info'] = $info;
            $logsArray['description'] = $description;
            $logsArray['action'] = '';
            $logsArray['message_type'] = 'error';
            $logsArray['log_action'] = 'fail';
            $logsArray['website_id'] = $this->storeManager->getStore($storeId)->getWebsiteId();
            $this->emarsysLog->manualLogs($logsArray);
        } catch (\Exception $e) {
            $this->messageManagerInterface->addErrorMessage(
                'Unable to Log: ' . $e->getMessage()
            );
        }
    }

    /**
     * @param $messages
     * @param $description
     * @param $storeId
     * @param $info
     */
    public function addNoticeLog($messages = '', $description = '', $storeId = 0, $info = '')
    {
        try {
            $logsArray['job_code'] = 'Notice';
            $logsArray['status'] = 'notice';
            $logsArray['messages'] = $messages;
            $logsArray['created_at'] = $this->dateTime->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->dateTime->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = '';
            $logsArray['auto_log'] = '';
            $logsArray['store_id'] = $storeId;
            $logsArray['emarsys_info'] = $info;
            $logsArray['description'] = $description;
            $logsArray['action'] = '';
            $logsArray['message_type'] = 'notice';
            $logsArray['log_action'] = 'fail';
            $logsArray['website_id'] = $this->storeManager->getStore($storeId)->getWebsiteId();
            $this->emarsysLog->manualLogs($logsArray);
        } catch (\Exception $e) {
            $this->messageManagerInterface->addErrorMessage(
                'Unable to Log: ' . $e->getMessage()
            );
        }
    }

    /**
     * @param $messages
     * @param $description
     * @param $storeId
     * @param $info
     */
    public function addSuccessLog($messages = '', $description = '', $storeId = 0, $info = '')
    {
        try {
            $logsArray['job_code'] = 'Success';
            $logsArray['status'] = 'success';
            $logsArray['messages'] = $messages;
            $logsArray['created_at'] = $this->dateTime->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->dateTime->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = '';
            $logsArray['auto_log'] = '';
            $logsArray['store_id'] = $storeId;
            $logsArray['emarsys_info'] = $info;
            $logsArray['description'] = $description;
            $logsArray['action'] = '';
            $logsArray['message_type'] = 'success';
            $logsArray['log_action'] = 'fail';
            $logsArray['website_id'] = $this->storeManager->getStore($storeId)->getWebsiteId();
            $this->emarsysLog->manualLogs($logsArray);
        } catch (\Exception $e) {
            $this->messageManagerInterface->addErrorMessage(
                'Unable to Log: ' . $e->getMessage()
            );
        }
    }
}
