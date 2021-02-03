<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Helper;

use Emarsys\Emarsys\Model\LogScheduleFactory;
use Emarsys\Emarsys\Model\LogsFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\App\Filesystem\DirectoryList;

class Logs extends AbstractHelper
{
    /**
     * @var LogScheduleFactory
     */
    protected $logScheduleFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var LogsFactory
     */
    protected $logsFactory;

    /**
     * @var null
     */
    protected $cronSchedule = null;

    /**
     * @var File
     */
    protected $file;

    /**
     * Logs constructor.
     *
     * @param Context $context
     * @param LogScheduleFactory $logScheduleFactory
     * @param LogsFactory $logsFactory
     * @param File $file
     */
    public function __construct(
        Context $context,
        LogScheduleFactory $logScheduleFactory,
        LogsFactory $logsFactory,
        File $file
    ) {
        $this->logScheduleFactory = $logScheduleFactory;
        $this->scopeConfigInterface = $context->getScopeConfig();
        $this->logsFactory = $logsFactory;
        $this->file = $file;
        parent::__construct($context);
    }

    /**
     * @param array $logsArray
     * @param int $exportCron
     * @return string
     */
    public function manualLogs($logsArray = [], $exportCron = 0)
    {
        try {
            if (!$this->scopeConfigInterface->getValue('logs/log_setting/enable')) {
                return;
            }
            if (!$this->cronSchedule || $exportCron) {
                $this->cronSchedule = $this->logScheduleFactory->create();
            }

            if (isset($logsArray['job_code'])
                && (empty($this->cronSchedule->getJobCode())
                    || $this->cronSchedule->getJobCode() == 'Exception'
                    || $this->cronSchedule->getJobCode() == 'Notice')
            ) {
                $this->cronSchedule->setJobCode($logsArray['job_code']);
            }

            if (isset($logsArray['schedule_id'])) {
                $this->cronSchedule->setScheduleId($logsArray['schedule_id']);
            }

            if (isset($logsArray['status'])) {
                $this->cronSchedule->setStatus($logsArray['status']);
            }

            if (isset($logsArray['messages']) && empty($this->cronSchedule->getMessages())) {
                $this->cronSchedule->setMessages(str_replace(',"', ' ,"', $logsArray['messages']));
            }

            if (isset($logsArray['created_at'])) {
                $this->cronSchedule->setCreatedAt($logsArray['created_at']);
            }

            if (isset($logsArray['executed_at'])) {
                $this->cronSchedule->setExecutedAt($logsArray['executed_at']);
            }

            if (isset($logsArray['finished_at'])) {
                $this->cronSchedule->setFinishedAt($logsArray['finished_at']);
            }

            if (isset($logsArray['run_mode'])) {
                $this->cronSchedule->setRunMode($logsArray['run_mode']);
            }

            if (isset($logsArray['auto_log'])) {
                $this->cronSchedule->setAutoLog($logsArray['auto_log']);
            }

            if (isset($logsArray['store_id'])) {
                $this->cronSchedule->setStoreId($logsArray['store_id']);
            }

            $this->cronSchedule->save();

            $id = $this->cronSchedule->getId();

            $logsArray['id'] = $id;

            if ((isset($logsArray['emarsys_info']) && !empty($logsArray['emarsys_info']))
                || (isset($logsArray['description']) && !empty($logsArray['description']))
            ) {
                $this->logs($logsArray);
            }
        } catch (\Exception $e) {
            $this->_logger->critical($e->getMessage());
            $id = null;
        }

        return $id;
    }

    /**
     * For saving Logs
     *
     * @param array $logsArray
     * @return true
     */
    public function logs($logsArray = [])
    {
        try {
            if (!$this->scopeConfigInterface->getValue('logs/log_setting/enable')) {
                return true;
            }
            $data = [
                'log_exec_id' => $logsArray['id'] ?? null,
                'created_at' => date('Y-m-d H:i:s', time()),
                'emarsys_info' => $logsArray['emarsys_info'] ?? '',
                'description' => $logsArray['description'] ?? '',
                'action' => $logsArray['action'] ?? 'synced to emarsys',
                'message_type' => $logsArray['message_type'] ?? '',
                'store_id' => $logsArray['store_id'] ?? 0,
                'website_id' => $logsArray['website_id'] ?? 0,
                'log_action' => $logsArray['log_action'] ?? 'sync',
            ];

            if ($this->scopeConfigInterface->getValue('logs/log_setting/save_to_file')) {
                $dirConfig = DirectoryList::getDefaultConfig();
                $fileDirectory = $dirConfig[DirectoryList::LOG][DirectoryList::PATH];

                $name = 'emarsys_cron_log' . date("Y-m-d") . '.csv';
                $file = $fileDirectory . '/' . $name;

                $fh = fopen($file, 'a');
                $this->file->filePutCsv($fh, $data);
                return true;
            }
            $logsModel = $this->logsFactory->create();
            $logsModel->setData($data);
            $logsModel->save();
        } catch (\Exception $e) {
            $this->_logger->critical($e->getMessage());
        }
        return true;
    }
}
