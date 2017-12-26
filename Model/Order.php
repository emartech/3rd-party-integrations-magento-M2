<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Emarsys\Emarsys\Helper\Logs as EmarsysHelperLogs;
use Emarsys\Emarsys\Model\ResourceModel\Customer as EmarsysResourceModelCustomer;
use Magento\Store\Model\StoreManagerInterface;
use Emarsys\Emarsys\Helper\Data as EmarsysDataHelper;
use Emarsys\Emarsys\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Sales\Model\Order\CreditmemoRepository;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\ScopeInterface;
use Emarsys\Emarsys\Model\OrderQueueFactory;
use Emarsys\Emarsys\Model\CreditmemoExportStatusFactory;
use Emarsys\Emarsys\Model\OrderExportStatusFactory;
use Magento\Framework\Stdlib\DateTime\Timezone as TimeZone;

/**
 * Class Order
 * @package Emarsys\Emarsys\Model
 */
class Order extends AbstractModel
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    /**
     * @var Customer
     */
    protected $customerResourceModel;

    /**
     * @var Logs
     */
    protected $logsHelper;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var EmarsysDataHelper
     */
    protected $emarsysDataHelper;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var CreditmemoRepository
     */
    protected $creditmemoRepository;

    /**
     * @var OrderFactory
     */
    protected $salesOrderCollectionFactory;

    /**
     * @var OrderQueueFactory
     */
    protected $orderQueueFactory;

    /**
     * @var CreditmemoExportStatusFactory
     */
    protected $creditmemoExportStatusFactory;

    /**
     * @var OrderExportStatusFactory
     */
    protected $orderExportStatusFactory;

    /**
     * @var TimeZone
     */
    protected $timezone;

    /**
     * Order constructor.
     * @param Context $context
     * @param Registry $registry
     * @param StoreManagerInterface $storeManager
     * @param MessageManagerInterface $messageManager
     * @param Customer $customerResourceModel
     * @param Logs $logsHelper
     * @param DateTime $date
     * @param EmarsysDataHelper $emarsysDataHelper
     * @param OrderResourceModel $orderResourceModel
     * @param CreditmemoRepository $creditmemoRepository
     * @param OrderFactory $salesOrderCollectionFactory
     * @param OrderQueueFactory $orderQueueFactory
     * @param CreditmemoExportStatusFactory $creditmemoExportStatusFactory
     * @param OrderExportStatusFactory $orderExportStatusFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        StoreManagerInterface $storeManager,
        MessageManagerInterface $messageManager,
        EmarsysResourceModelCustomer $customerResourceModel,
        EmarsysHelperLogs $logsHelper,
        DateTime $date,
        EmarsysDataHelper $emarsysDataHelper,
        OrderResourceModel $orderResourceModel,
        CreditmemoRepository $creditmemoRepository,
        OrderFactory $salesOrderCollectionFactory,
        OrderQueueFactory $orderQueueFactory,
        CreditmemoExportStatusFactory $creditmemoExportStatusFactory,
        OrderExportStatusFactory $orderExportStatusFactory,
        TimeZone $timezone,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->messageManager = $messageManager;
        $this->customerResourceModel = $customerResourceModel;
        $this->logsHelper = $logsHelper;
        $this->date = $date;
        $this->emarsysDataHelper =  $emarsysDataHelper;
        $this->orderResourceModel = $orderResourceModel;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->salesOrderCollectionFactory = $salesOrderCollectionFactory;
        $this->orderQueueFactory = $orderQueueFactory;
        $this->creditmemoExportStatusFactory = $creditmemoExportStatusFactory;
        $this->orderExportStatusFactory = $orderExportStatusFactory;
        $this->timezone = $timezone;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * constructor
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('Emarsys\Emarsys\Model\ResourceModel\Order');
    }

    /**
     * @param $storeId
     * @param $mode
     * @param null $exportFromDate
     * @param null $exportTillDate
     */
    public function syncOrders($storeId, $mode, $exportFromDate = null, $exportTillDate = null)
    {
        $store = $this->storeManager->getStore($storeId);
        $websiteId = $store->getWebsiteId();
        $storeCode = $store->getCode();
        $scope = ScopeInterface::SCOPE_WEBSITE;
        $creditmemoOrderIds = [];
        $creditMemoCollection = $this->creditmemoRepository->create()->getCollection();

        //validate date range (Bulk export)
        if ($mode == EmarsysDataHelper::ENTITY_EXPORT_MODE_MANUAL) {
            if (isset($exportFromDate) && $exportFromDate != '') {
                $toTimezone = $this->timezone->getDefaultTimezone();
                $fromDate = $this->timezone->date($exportFromDate)
                    ->setTimezone(new \DateTimeZone($toTimezone))
                    ->format('Y-m-d H:i:s');
                $magentoTime = $this->date->date('Y-m-d H:i:s');
                $currentTime = new \DateTime($magentoTime);
                $currentTime->format('Y-m-d H:i:s');
                $datetime2 = new \DateTime($fromDate);
                $interval = $currentTime->diff($datetime2);
                if ($interval->y > 2 || ($interval->y == 2 && $interval->m >= 1) || ($interval->y == 2 && $interval->d >= 1)) {
                    $this->messageManager->addErrorMessage(__("The timeframe cannot be more than 2 years"));
                    return;
                }
            }
        }

        //Loging functionality start
        $logsArray['job_code'] = 'order';
        $logsArray['status'] = 'started';
        $logsArray['messages'] = __('Bulk order export started');
        $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['run_mode'] = $mode;
        $logsArray['auto_log'] = 'Complete';
        $logsArray['store_id'] = $storeId;
        $logsArray['website_id'] = $websiteId;
        $logId = $this->logsHelper->manualLogs($logsArray, 1);

        $isEmarsysEnabledForStore = $this->customerResourceModel->getDataFromCoreConfig(
            'emarsys_settings/emarsys_setting/enable',
            $scope,
            $websiteId
        );

        if (!$isEmarsysEnabledForStore) {
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = __('Emarsys is disabled');
            $logsArray['description'] = __('Emarsys is disabled for the store %1', $store->getName());
            $logsArray['action'] = 'synced to emarsys';
            $logsArray['message_type'] = 'Error';
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->logs($logsArray);
            $logsArray['status'] = 'error';
            $logsArray['messages'] = __('Order export Failed');
            $this->messageManager->addErrorMessage(__('Emarsys is disabled for the store %1', $store->getName()));
            $this->logsHelper->manualLogsUpdate($logsArray);

            return;
        }

        $smartInsightEnabled = $this->customerResourceModel->getDataFromCoreConfig(
            EmarsysDataHelper::XPATH_SMARTINSIGHT_ENABLED,
            $scope,
            $websiteId
        );

        //Update logs and return if Smart Insight is Not enabled for any website.
        if (!$smartInsightEnabled) {
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = __('Emarsys is Disabled for this website %1', $websiteId);
            $logsArray['description'] = __('Emarsys is Disabled for this website %1', $websiteId);
            $logsArray['action'] = 'Smart Insight';
            $logsArray['message_type'] = 'Error';
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->logs($logsArray);
            if ($mode == EmarsysDataHelper::ENTITY_EXPORT_MODE_MANUAL) {
                $this->messageManager->addErrorMessage(
                    __('Smart Insight is Disabled for the website %1', $websiteId)
                );
            }
            return;
        }

        //Collect FTP Credentials
        $hostname = $this->customerResourceModel->getDataFromCoreConfig(EmarsysDataHelper::XPATH_EMARSYS_FTP_HOSTNAME, $scope, $websiteId);
        $port = $this->customerResourceModel->getDataFromCoreConfig(EmarsysDataHelper::XPATH_EMARSYS_FTP_PORT, $scope, $websiteId);
        $username = $this->customerResourceModel->getDataFromCoreConfig(EmarsysDataHelper::XPATH_EMARSYS_FTP_USERNAME, $scope, $websiteId);
        $password = $this->customerResourceModel->getDataFromCoreConfig(EmarsysDataHelper::XPATH_EMARSYS_FTP_PASSWORD, $scope, $websiteId);
        $bulkDir = $this->customerResourceModel->getDataFromCoreConfig(EmarsysDataHelper::XPATH_EMARSYS_FTP_BULK_EXPORT_DIR, $scope, $websiteId);
        $ftpSsl = $this->customerResourceModel->getDataFromCoreConfig(EmarsysDataHelper::XPATH_EMARSYS_FTP_USEFTP_OVER_SSL, $scope, $websiteId);
        $passiveMode = $this->customerResourceModel->getDataFromCoreConfig(EmarsysDataHelper::XPATH_EMARSYS_FTP_USE_PASSIVE_MODE, $scope, $websiteId);

        if ($hostname != '' && $port != '' && $username != '' && $password != '') {
            $errorStatus = 0;
        } else {
            $errorStatus = 1;
        }

        if ($errorStatus != 1) {
            $checkFtpConnection = $this->emarsysDataHelper->checkFtpConnection(
                $hostname,
                $username,
                $password,
                $port,
                $ftpSsl,
                $passiveMode
            );

            if ($checkFtpConnection) {
                $heading = ['order', 'date', 'customer', 'item', 'unit_price', 'c_sales_amount', 'quantity'];
                $emasysFields = $this->orderResourceModel->getEmarsysOrderFields();
                foreach ($emasysFields as $field) {
                    $emarsysOrderFieldValue = trim($field['emarsys_order_field']);
                    if ($emarsysOrderFieldValue != '' && $emarsysOrderFieldValue != "'") {
                        $heading[] = $emarsysOrderFieldValue;
                    }
                }

                $localFilePath = BP . "/var";
                $outputFile = "sales_items_" . $this->date->date('YmdHis', time()) . "_" . $storeCode . ".csv";
                $filePath = $localFilePath . "/" . $outputFile;
                $handle = fopen($filePath, 'w');
                fputcsv($handle, $heading);

                $orderStatuse = $this->customerResourceModel->getDataFromCoreConfig(
                    EmarsysDataHelper::XPATH_SMARTINSIGHT_EXPORT_ORDER_STATUS,
                    $scope,
                    $websiteId
                );
                $orderStatuses = explode(',', $orderStatuse);
                $orderCollection = [];
                $guestOrderExportStatus = $this->customerResourceModel->getDataFromCoreConfig(
                    EmarsysDataHelper::XPATH_SMARTINSIGHT_EXPORTGUEST_CHECKOUTORDERS,
                    $scope,
                    $websiteId
                );
                $emailAsIdentifierStatus = $this->customerResourceModel->getDataFromCoreConfig(
                    EmarsysDataHelper::XPATH_SMARTINSIGHT_EXPORTUSING_EMAILIDENTIFIER,
                    $scope,
                    $websiteId
                );

                if ($mode == EmarsysDataHelper::ENTITY_EXPORT_MODE_AUTOMATIC) {
                    $queueCollection = $this->orderQueueFactory->create();
                    $queueDataAll = $queueCollection->getCollection()
                                    ->addFieldToFilter('entity_type_id', 1)
                                    ->getData();
                    $queueCollection = $this->orderQueueFactory->create();
                    $creditMemoCollection = $queueCollection->getCollection()
                                    ->addFieldToFilter('entity_type_id', 2)->getData();
                    $orderIds = [];
                    foreach ($queueDataAll as $queueData) {
                        $orderIds[] = $queueData['entity_id'];
                    }
                    foreach ($creditMemoCollection as $queueData) {
                        $creditmemoOrderIds[] = $queueData['entity_id'];
                    }
                    $orderCollection = $this->salesOrderCollectionFactory->create()->getCollection()
                        ->addFieldToFilter('store_id', $store['store_id'])
                        ->addFieldToFilter('entity_id', ['in' => $orderIds]);
                } else {
                    if (isset($exportFromDate) && isset($exportTillDate) && $exportFromDate != '' && $exportTillDate != '') {
                        $toTimezone = $this->timezone->getDefaultTimezone();
                        $fromDate = $this->timezone->date($exportFromDate)
                            ->setTimezone(new \DateTimeZone($toTimezone))
                            ->format('Y-m-d H:i:s');

                        $toDate = $this->timezone->date($exportTillDate)
                            ->setTimezone(new \DateTimeZone($toTimezone))
                            ->format('Y-m-d H:i:s');

                        if ($orderStatuses != '') {
                            $orderCollection = $this->salesOrderCollectionFactory->create()->getCollection()
                                ->addFieldToFilter(
                                    'created_at',
                                    [
                                        'from' => $fromDate,
                                        'to' => $toDate,
                                        'date' => true,
                                    ]
                                )
                                ->addFieldToFilter('store_id', ['eq' => $storeId])
                                ->addFieldToFilter('status', ['in' => $orderStatuses]);
                            $creditMemoCollection = $this->creditmemoRepository->create()->getCollection()
                                ->addFieldToFilter(
                                    'created_at',
                                    [
                                        'from' => $fromDate,
                                        'to' => $toDate,
                                        'date' => true,
                                    ]
                                )
                                ->addFieldToFilter('store_id', ['eq' => $storeId]);
                        }
                    } elseif (isset($exportFromDate) && isset($exportTillDate) && $exportFromDate == '' && $exportTillDate == '') {
                        if ($orderStatuses != '') {
                            $orderCollection = $this->salesOrderCollectionFactory->create()
                                ->getCollection()
                                ->addAttributeToFilter('status', ['in' => $orderStatuses])
                                ->addFieldToFilter('store_id', ['eq' => $storeId]);
                        }
                        $creditMemoCollection = $this->creditmemoRepository->create()->getCollection()
                                                ->addFieldToFilter('store_id', ['eq' => $storeId]);
                    }
                }

                foreach ($orderCollection as $order) {
                    $orderId = $order->getRealOrderId();
                    $orderEntityId = $order->getId();
                    $createdDate = $order->getCreatedAt();
                    $customerEmail = $order->getCustomerEmail();

                    foreach ($order->getAllVisibleItems() as $item) {
                        $values = [];
                        $values[] = $orderId;
                        $date = new \DateTime($createdDate);
                        $createdDate = $date->format('Y-m-d');
                        $values[] = $createdDate;
                        if ($customerEmail != '') {
                            $values[] = $customerEmail;
                        } else {
                            $values[] = '';
                        }
                        $sku = $item->getSku();
                        $product = $item->getProduct();
                        if (!is_null($product) && is_object($product)) {
                            if ($product->getId()) {
                                $sku = $product->getSku();
                            }
                        }
                        $values[] = $sku;
                        //Unit Prices
                        $unitPrice = $item->getPriceInclTax();
                        if ($unitPrice != '') {
                            $values[] = number_format((float)$unitPrice, 2, '.', '') ;
                        } else {
                            $values[] = '';
                        }
                        //cSalesAmount
                        $cSalesAmount = $item->getRowTotalInclTax() - $item->getDiscountAmount();
                        if ($cSalesAmount != '') {
                            $values[] = $cSalesAmount;
                        } else {
                            $values[] = '';
                        }
                        $values[] = (int)$item->getQtyInvoiced();
                        foreach ($emasysFields as $field) {
                            $emarsysOrderFieldValueOrder = trim($field['emarsys_order_field']);
                            if ($emarsysOrderFieldValueOrder != '' && $emarsysOrderFieldValueOrder != "'") {
                                $orderExpValues = $this->orderResourceModel->getOrderColValue(
                                    $emarsysOrderFieldValueOrder,
                                    $orderEntityId
                                );
                                if (isset($orderExpValues['created_at'])) {
                                    $createdAt = $this->emarsysDataHelper->getDateTimeInLocalTimezone($orderExpValues['created_at']);
                                    $values[] = $createdAt;
                                } elseif (isset($orderExpValues['updated_at'])) {
                                    $updatedAt = $this->emarsysDataHelper->getDateTimeInLocalTimezone($orderExpValues['updated_at']);
                                    $values[] = $updatedAt;
                                } else {
                                    $values[] = $orderExpValues['magento_column_value'];
                                }
                            }
                        }
                        if (($guestOrderExportStatus == 0 || $emailAsIdentifierStatus == 0) && $order->getCustomerIsGuest() == 1) {
                        } else {
                            fputcsv($handle, $values);
                        }
                    }
                }

                foreach ($creditMemoCollection as $creditMemo) {
                    $creditMemoOrder = $this->salesOrderCollectionFactory->create()->load($creditMemo['order_id']);
                    $orderId = $creditMemoOrder->getIncrementId();
                    $orderEntityId = $creditMemoOrder->getId();
                    $createdDate = $creditMemoOrder->getCreatedAt();
                    $customerEmail = $creditMemoOrder->getCustomerEmail();
                    foreach ($creditMemo->getAllItems() as $item) {
                        if ($item->getOrderItem()->getParentItem()) continue;
                        $values = [];
                        $values[] = $orderId;
                        $date = new \DateTime($createdDate);
                        $createdDate = $date->format('Y-m-d');
                        $values[] = $createdDate;
                        if ($customerEmail != '') {
                            $values[] = $customerEmail;
                        } else {
                            $values[] = '';
                        }
                        $csku = $item->getSku();
                        $creditMemoProduct = $item->getProduct();
                        if (!is_null($creditMemoProduct) && is_object($creditMemoProduct)) {
                            if ($creditMemoProduct->getId()) {
                                $csku = $creditMemoProduct->getSku();
                            }
                        }
                        $values[] = $csku;

                        //Unit Prices
                        $unitPrice = $item->getPriceInclTax();
                        if ($unitPrice != '') {
                            $values[] = "-" . number_format((float)$unitPrice, 2, '.', '');
                        } else {
                            $values[] = '';
                        }
                        //cSalesAmount
                        $cSalesAmount = $item->getRowTotalInclTax() - $item->getDiscountAmount();
                        if ($cSalesAmount != '') {
                            $values[] = "-" . $cSalesAmount;
                        } else {
                            $values[] = '';
                        }
                        $values[] = (int)$item->getQty();

                        foreach ($emasysFields as $field) {
                            $emarsysOrderFieldValueCm = trim($field['emarsys_order_field']);
                            if ($emarsysOrderFieldValueCm != '' && $emarsysOrderFieldValueCm != "'") {
                                $orderExpValues = $this->orderResourceModel->getOrderColValue(
                                    $emarsysOrderFieldValueCm,
                                    $orderEntityId
                                );

                                if (isset($orderExpValues['created_at'])) {
                                    $createdAt = $this->emarsysDataHelper->getDateTimeInLocalTimezone($orderExpValues['created_at']);
                                    $values[] = $createdAt;
                                } elseif (isset($orderExpValues['updated_at'])) {
                                    $updatedAt = $this->emarsysDataHelper->getDateTimeInLocalTimezone($orderExpValues['updated_at']);
                                    $values[] = $updatedAt;
                                } else {
                                    $values[] = $orderExpValues['magento_column_value'];
                                }
                            }
                        }
                        if (($guestOrderExportStatus == 0 || $emailAsIdentifierStatus == 0) && $creditMemoOrder->getCustomerIsGuest() == 1) {
                        } else {
                            fputcsv($handle, $values);
                        }
                    }

                    //if creditmemo have adjustments
                    if ($creditMemo->getAdjustment() != 0 ) {
                        $values = [];
                        //set order id
                        $values[] = $orderId;
                        $date = new \DateTime($createdDate);
                        $createdDate = $date->format('Y-m-d');
                        //set timestamp
                        $values[] = $createdDate;
                        if ($customerEmail != '') {
                            $values[] = $customerEmail;
                        } else {
                            $values[] = '';
                        }
                        //set item id/sku
                        $values[] = 0;

                        //set Unit Prices
                        $values[] = $creditMemo->getAdjustment();

                        //set cSalesAmount
                        $values[] = $creditMemo->getAdjustment();

                        //set quantity
                        $values[] = 1;

                        foreach ($emasysFields as $field) {
                            $emarsysOrderFieldValueAdjustment = trim($field['emarsys_order_field']);
                            if ($emarsysOrderFieldValueAdjustment != '' && $emarsysOrderFieldValueAdjustment != "'") {
                                $orderExpValues = $this->orderResourceModel->getOrderColValue(
                                    $emarsysOrderFieldValueAdjustment,
                                    $orderEntityId
                                );

                                if (isset($orderExpValues['created_at'])) {
                                    $createdAt = $this->emarsysDataHelper->getDateTimeInLocalTimezone($orderExpValues['created_at']);
                                    $values[] = $createdAt;
                                } elseif (isset($orderExpValues['updated_at'])) {
                                    $updatedAt = $this->emarsysDataHelper->getDateTimeInLocalTimezone($orderExpValues['updated_at']);
                                    $values[] = $updatedAt;
                                } else {
                                    $values[] = $orderExpValues['magento_column_value'];
                                }
                            }
                        }
                        if (!(($guestOrderExportStatus == 0 || $emailAsIdentifierStatus == 0) && $creditMemoOrder->getCustomerIsGuest() == 1)) {
                            fputcsv($handle, $values);
                        }
                    }
                }

                $file = $outputFile;
                $fileOpen = fopen($filePath, "r");
                $remoteDirPath = $bulkDir;
                if ($remoteDirPath == '/') {
                    $remoteFileName = $outputFile;
                } else {
                    $remoteDirPath = rtrim($remoteDirPath, '/');
                    $remoteFileName = $remoteDirPath . "/" . $outputFile;
                }

                if ($ftpSsl == 1) {
                    $ftpConnection = @ftp_ssl_connect($hostname, $port);
                } else {
                    $ftpConnection = @ftp_connect($hostname, $port);
                }
                $ftpLogin = @ftp_login($ftpConnection, $username, $password);

                if ($passiveMode == 1) {
                    @ftp_pasv($ftpConnection, true);
                }

                //Create remote directory if not present
                if (!@ftp_chdir($ftpConnection, $remoteDirPath)) {
                    @ftp_mkdir($ftpConnection, $remoteDirPath);
                }
                @ftp_chdir($ftpConnection, '/');

                //Upload CSV to FTP
                if (@ftp_put($ftpConnection, $remoteFileName, $filePath, FTP_ASCII)) {
                    $logsArray['id'] = $logId;
                    $logsArray['emarsys_info'] = __('File uploaded to FTP server successfully');
                    $logsArray['description'] = $remoteFileName;
                    $logsArray['action'] = 'synced to FTP';
                    $logsArray['message_type'] = 'Success';
                    $logsArray['log_action'] = 'sync';
                    $this->logsHelper->logs($logsArray);
                    $errorCount = 0;
                    if ($mode == EmarsysDataHelper::ENTITY_EXPORT_MODE_MANUAL) {
                        $this->messageManager->addSuccessMessage(
                            __("File uploaded to FTP server successfully !!!")
                        );
                    }
                } else {
                    $errorMessage = error_get_last();
                    $msg = isset($errorMessage['message']) ? $errorMessage['message'] : '';
                    $logsArray['id'] = $logId;
                    $logsArray['emarsys_info'] = __('Failed to upload file on FTP server');
                    $logsArray['description'] = __('Failed to upload file on FTP server. %1', $msg);
                    $logsArray['action'] = 'synced to FTP';
                    $logsArray['message_type'] = 'Error';
                    $logsArray['log_action'] = 'sync';
                    $this->logsHelper->logs($logsArray);
                    $errorCount = 1;
                    if ($mode == EmarsysDataHelper::ENTITY_EXPORT_MODE_MANUAL) {
                        $this->messageManager->addErrorMessage(
                            __("Failed to upload file on FTP server !!! %1", $msg)
                        );
                    }
                }
                unlink($filePath);
                $errorCount = 0;
            } else {
                $logsArray['id'] = $logId;
                $logsArray['emarsys_info'] = __('Failed to connect with FTP server.');
                $logsArray['description'] = __('Failed to connect with FTP server.');
                $logsArray['action'] = 'synced to FTP';
                $logsArray['message_type'] = 'Error';
                $logsArray['log_action'] = 'sync';
                $this->logsHelper->logs($logsArray);
                $errorCount = 1;
                if ($mode == EmarsysDataHelper::ENTITY_EXPORT_MODE_MANUAL) {
                    $this->messageManager->addErrorMessage(
                        __('"Failed to connect with FTP server. Please check your settings and try again !!!"')
                    );
                }
            }
        } else {
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = __('Invalid FTP credentials');
            $logsArray['description'] = __('Invalid FTP credential. Please check your settings and try again');
            $logsArray['action'] = 'synced to FTP';
            $logsArray['message_type'] = 'Error';
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->logs($logsArray);
            $errorCount = 1;
            if ($mode == EmarsysDataHelper::ENTITY_EXPORT_MODE_MANUAL) {
                $this->messageManager->addErrorMessage(
                    __("Invalid FTP credential. Please check your settings and try again !!!")
                );
            }
        }

        $logsArray['id'] = $logId;
        $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
        if ($errorCount == 1) {
            $logsArray['status'] = 'error';
            $logsArray['messages'] = __('Order export have an error. Please check');
        } else {
            if ($mode == EmarsysDataHelper::ENTITY_EXPORT_MODE_AUTOMATIC) {
                $orderExportStatus = $this->orderExportStatusFactory->create();
                $orderExportStatusCollection = $orderExportStatus->getCollection()
                    ->addFieldToFilter('order_id', ['in' => $orderIds]);
                $allDataExport = $orderExportStatusCollection->getData();

                foreach ($allDataExport as $orderExportStat) {
                    $eachOrderStat = $this->orderExportStatusFactory->create()->load($orderExportStat['id']);
                    $eachOrderStat->setExported(1);
                    $eachOrderStat->save();
                }
                //Remove the exported records from the queue table
                foreach ($queueDataAll as $queueData) {
                    $queueDataEach = $this->orderQueueFactory->create()->load($queueData['id']);
                    $queueDataEach->delete();
                }

                $creditmemoExportStatus = $this->creditmemoExportStatusFactory->create();
                $creditmemoExportStatusCollection = $creditmemoExportStatus->getCollection()
                    ->addFieldToFilter('order_id', ['in' => $creditmemoOrderIds]);
                $allDataExport = $creditmemoExportStatusCollection->getData();
                foreach ($allDataExport as $orderExportStat) {
                    $eachOrderStat = $this->creditmemoExportStatusFactory->create()->load($orderExportStat['id']);
                    $eachOrderStat->setExported(1);
                    $eachOrderStat->save();
                }
                //Remove the exported records from the queue table
                foreach ($creditMemoCollection as $queueData) {
                    $queueDataEach = $this->orderQueueFactory->create()->load($queueData['id']);
                    $queueDataEach->delete();
                }
            }
            $logsArray['status'] = 'success';
            $logsArray['messages'] = __('Order export completed');
        }
        $this->logsHelper->manualLogsUpdate($logsArray);

        return;
    }
}
