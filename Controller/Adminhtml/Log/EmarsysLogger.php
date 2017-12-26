<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Log;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface as Logger;

class EmarsysLogger extends \Magento\Backend\App\Action
{

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $date;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    protected $file;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var \Magento\Backend\Helper\Data
     */
    protected $backendHelper;


    /**
     * 
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Filesystem\Io\File $file
     * @param PageFactory $resultPageFactory
     * @param \Emarsys\Emarsys\Helper\Data $dataHelper
     * @param \Emarsys\Emarsys\Model\ResourceModel\Customer $customerResourceModel
     * @param \Magento\Framework\Controller\Result\RawFactory $resultRawFactory
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     * @param Context $context
     * @param \Magento\Framework\App\Request\Http $request
     * @param Logger $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Filesystem\Io\File $file,
        PageFactory $resultPageFactory,
        \Emarsys\Emarsys\Helper\Data $dataHelper,
        \Emarsys\Emarsys\Model\ResourceModel\Customer $customerResourceModel,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Request\Http $request,
        Logger $logger
    ) {
        parent::__construct($context);
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->resultPageFactory = $resultPageFactory;
        $this->date = $date;
        $this->backendHelper = $context->getHelper();
        $this->dataHelper = $dataHelper;
        $this->storeManager = $storeManager;
        $this->file = $file;
        $this->logger = $logger;
        $this->request = $request;
        $this->resultRawFactory = $resultRawFactory;
        $this->customerResourceModel = $customerResourceModel;
        $this->fileFactory = $fileFactory;
        parent::__construct($context);
    }

    /**
     * Index action
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */

    public function execute()
    {
        $params = $this->request->getParams();
        $websiteId = $params['website'];
        $logData = $this->customerResourceModel->getLogsData();
        $filePath = BP . "/var/log/emarsys.log";
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $writer = new \Zend\Log\Writer\Stream($filePath);
        $logger = new \Zend\Log\Logger();
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
