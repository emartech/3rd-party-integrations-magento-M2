<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Log;

use Exception;
use Magento\Backend\App\Action\Context;
use Emarsys\Emarsys\Model\Logs;
use Emarsys\Emarsys\Model\ResourceModel\LogSchedule;
use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;

class ClearLogs extends Action
{
    /**
     * @var Logs
     */
    protected $logs;

    /**
     * @var LogSchedule
     */
    protected $logSchedule;

    /**
     * ClearLogs constructor.
     *
     * @param Context $context
     * @param Logs $logs
     * @param LogSchedule $logSchedule
     */
    public function __construct(
        Context $context,
        Logs $logs,
        LogSchedule $logSchedule
    ) {
        parent::__construct($context);
        $this->logs = $logs;
        $this->logSchedule = $logSchedule;
    }

    /**
     * @return Redirect
     * @throws LocalizedException
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setRefererOrBaseUrl();

        try {
            $connection = $this->logSchedule->getConnection();
            $tableName = $this->logSchedule->getMainTable();
            $connection->delete($tableName);

            $this->messageManager->addSuccessMessage(__('Log tables have been truncated successfully.'));
        } catch (Exception $e) {
            $this->logs->addErrorLog(
                'ClearLogs',
                $e->getMessage(),
                0,
                'ClearLogs'
            );
            $this->messageManager->addErrorMessage(__('Something went wrong while deleting logs.'));
        }

        return $resultRedirect;
    }
}
