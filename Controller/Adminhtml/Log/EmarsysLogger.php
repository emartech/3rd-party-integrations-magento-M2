<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Emarsys\Emarsys\Model\ResourceModel\Logs;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

class EmarsysLogger extends Action
{
    /**
     * @var Logs
     */
    protected $logsResourceModel;

    /**
     * EmarsysLogger constructor.
     *
     * @param Context $context
     * @param Logs $logsResourceModel
     */
    public function __construct(
        Context $context,
        Logs $logsResourceModel
    ) {
        parent::__construct($context);
        $this->logsResourceModel = $logsResourceModel;
    }

    /**
     * @return Redirect | false | int
     * @throws LocalizedException
     */
    public function execute()
    {
        $logData = $this->logsResourceModel->getLogsData();
        $filePath = BP . "/var/log/emarsys.log";
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $writer = new Stream($filePath);
        $logger = new Logger();
        $logger->addWriter($writer);
        foreach ($logData as $log) {
            $data = "[" . $log['created_at'] . "] : " . $log['message_type'] . " : " . $log['description'];
            $logger->info($data);
        }
        if (file_exists($filePath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . basename('emarsys.log'));
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
        } else {
            $this->messageManager->addSuccessMessage(__('No Logs Found'));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setRefererOrBaseUrl();

            return $resultRedirect;
        }
    }
}
