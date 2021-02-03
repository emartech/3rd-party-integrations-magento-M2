<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Helper;

use Emarsys\Emarsys\Controller\Adminhtml\Email\Template as ControllerTemplate;
use Emarsys\Emarsys\Helper\Logs as EmarsysHelperLogs;
use Emarsys\Emarsys\Model\Api\Api as EmarsysApiApi;
use Emarsys\Emarsys\Model\EmarsyseventmappingFactory as EmarsysEventMappingFactory;
use Emarsys\Emarsys\Model\Emarsysevents;
use Emarsys\Emarsys\Model\EmarsyseventsFactory;
use Emarsys\Emarsys\Model\Logs as EmarsysModelLogs;
use Emarsys\Emarsys\Model\PlaceholdersFactory;
use Emarsys\Emarsys\Model\Queue;
use Emarsys\Emarsys\Model\QueueFactory as EmarsysQueueFactory;
use Emarsys\Emarsys\Model\ResourceModel\Customer as ModelResourceModelCustomer;
use Emarsys\Emarsys\Model\ResourceModel\Emarsysevents\CollectionFactory as EmarsyseventsCollectionFactory;
use Emarsys\Emarsys\Model\ResourceModel\Emarsysmagentoevents\CollectionFactory;
use Emarsys\Emarsys\Model\ResourceModel\Event as ModelResourceModelEvent;
use Magento\Backend\Model\Session as BackendSession;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as ProductCollectionFactory;
use Magento\Email\Model\TemplateFactory as EmailTemplateFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

use Magento\Framework\Filesystem\Io\File as FilesystemIoFile;
use Magento\Framework\Filesystem\Io\Ftp;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\Timezone;
use Magento\Framework\UrlInterface;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\ResourceModel\Store\CollectionFactory as StoreCollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use Zend_Json;

class Data extends AbstractHelper
{
    const VERSION = '1.0.30';

    const EMARSYS_CDN_API_URL = 'https://api-cdn.emarsys.net/api/v2/';

    const EMARSYS_DEFAULT_API_URL = 'https://api.emarsys.net/api/v2/';

    //XML Path of Emarsys Credentials
    const XPATH_EMARSYS_ENABLED = 'emartech/emarsys_setting/enable';

    const XPATH_EMARSYS_API_ENDPOINT = 'emartech/emarsys_setting/emarsys_api_endpoint';

    const XPATH_EMARSYS_CUSTOM_URL = 'emartech/emarsys_setting/emarsys_custom_url';

    const XPATH_EMARSYS_API_USER = 'emartech/emarsys_setting/emarsys_api_username';

    const XPATH_EMARSYS_API_PASS = 'emartech/emarsys_setting/emarsys_api_password';

    const XPATH_EMARSYS_ASYNC_ENABLE = 'emartech/async/enable';

    //XML Path of FTP Credentials
    const XPATH_EMARSYS_FTP_HOSTNAME = 'emartech/ftp_settings/hostname';

    const XPATH_EMARSYS_FTP_PORT = 'emartech/ftp_settings/port';

    const XPATH_EMARSYS_FTP_USERNAME = 'emartech/ftp_settings/username';

    const XPATH_EMARSYS_FTP_PASSWORD = 'emartech/ftp_settings/ftp_password';

    const XPATH_EMARSYS_FTP_BULK_EXPORT_DIR = 'emartech/ftp_settings/ftp_bulk_export_dir';

    const XPATH_EMARSYS_FTP_USEFTP_OVER_SSL = 'emartech/ftp_settings/useftp_overssl';

    const XPATH_EMARSYS_FTP_USE_PASSIVE_MODE = 'emartech/ftp_settings/usepassive_mode';

    //Contacts Synchronization
    const XPATH_EMARSYS_ENABLE_CONTACT_FEED = 'contacts_synchronization/enable/contact_feed';

    const XPATH_EMARSYS_REALTIME_SYNC = 'contacts_synchronization/emarsys_emarsys/realtime_sync';

    const XPATH_EMARSYS_BACKGROUND_RUNTIME = 'contacts_synchronization/emarsys_emarsys/background_runtime';

    //Opt In Settings
    const XPATH_OPTIN_ENABLED = 'opt_in/optin_enable/enable_optin';

    const XPATH_OPTIN_EVERYPAGE_STRATEGY = 'opt_in/optin_enable/opt_in_strategy';

    const XPATH_OPTIN_SUBSCRIPTION_CHECKOUT = 'opt_in/subscription_checkout_process/newsletter_sub_checkout_yes_no';

    //Smart Insight
    const XPATH_SMARTINSIGHT_ENABLED = 'smart_insight/smart_insight/smartinsight_enabled';

    const XPATH_SMARTINSIGHT_EXPORTGUEST_CHECKOUTORDERS = 'smart_insight/smart_insight/exportguest_checkoutorders';

    //Smart Insight API Settings
    const XPATH_EMARSYS_SIEXPORT_API_ENABLED = 'smart_insight/api_settings/enableapi';

    const XPATH_EMARSYS_SIEXPORT_MERCHANT_ID = 'smart_insight/api_settings/merchant_id';

    const XPATH_EMARSYS_SIEXPORT_TOKEN = 'smart_insight/api_settings/token';

    const XPATH_EMARSYS_SIEXPORT_MAX_RECORDS = 'smart_insight/api_settings/max_records_per_export';

    const XPATH_EMARSYS_SIEXPORT_MAX_RECORDS_OPT = 'smart_insight/api_settings/max_records_per_export_option';

    const XPATH_EMARSYS_SIEXPORT_API_URL = 'smart_insight/api_settings/smartinsight_api_url';

    const XPATH_EMARSYS_SIEXPORT_API_URL_KEY = 'smart_insight/api_settings/smartinsight_order_api_url_key';

    const XPATH_EMARSYS_CATALOG_EXPORT_API_URL_KEY = 'smart_insight/api_settings/smartinsight_product_api_url_key';

    //Product Catalog
    const XPATH_PREDICT_ENABLE_NIGHTLY_PRODUCT_FEED = 'emarsys_predict/enable/product_feed';

    const XPATH_PREDICT_AVAILABILITY_STATUS = 'emarsys_predict/availability/status';

    const XPATH_PREDICT_AVAILABILITY_IN_STOCK = 'emarsys_predict/availability/in_stock';

    const XPATH_PREDICT_AVAILABILITY_VISIBILITY = 'emarsys_predict/availability/visibility';

    const XPATH_PREDICT_INCLUDE_BUNDLE_PRODUCT = 'emarsys_predict/feed_export/include_bundle_product';

    const XPATH_PREDICT_EXCLUDED_CATEGORIES = 'emarsys_predict/feed_export/excludedcategories';

    const XPATH_PREDICT_API_ENABLED = 'emarsys_predict/catalog_api_settings/enable_catalog_api';

    const XPATH_PREDICT_MERCHANT_ID = 'emarsys_predict/catalog_api_settings/catalog_merchant_id';

    const XPATH_PREDICT_TOKEN = 'emarsys_predict/catalog_api_settings/catalog_token';

    //WebExtend
    const XPATH_CMS_INDEX_INDEX = 'web_extend/recommended_product_pages/recommended_home_page';

    const XPATH_CATALOG_CATEGORY_VIEW = 'web_extend/recommended_product_pages/recommended_category_page';

    const XPATH_CATALOG_PRODUCT_VIEW = 'web_extend/recommended_product_pages/recommended_product_page';

    const XPATH_CHECKOUT_CART_INDEX = 'web_extend/recommended_product_pages/recommended_cart_page';

    const XPATH_CHECKOUT_ONEPAGE_SUCCESS = 'web_extend/recommended_product_pages/recommended_order_thankyou_page';

    const XPATH_CATALOGSEARCH_RESULT_INDEX = 'web_extend/recommended_product_pages/recommended_nosearch_result_page';

    const XPATH_WEBEXTEND_JS_TRACKING_ENABLED = 'web_extend/javascript_tracking/enable_javascript_integration';

    const XPATH_WEBEXTEND_MERCHANT_ID = 'web_extend/javascript_tracking/merchant_id';

    const XPATH_WEBEXTEND_MODE = 'web_extend/javascript_tracking/testmode';

    const XPATH_WEBEXTEND_UNIQUE_ID = 'web_extend/javascript_tracking/uniqueidentifier';

    const XPATH_WEBEXTEND_USE_BASE_CURRENCY = 'web_extend/javascript_tracking/use_base_currency';

    const XPATH_WEBEXTEND_INCLUDE_TAX = 'web_extend/javascript_tracking/tax_included';

    const ENTITY_EXPORT_MODE_AUTOMATIC = 'Automatic';

    const ENTITY_EXPORT_MODE_MANUAL = 'Manual';

    const EMARSYS_RELEASE_URL = 'about_emarsys/emarsys_release/release_url';

    const CUSTOMER_EMAIL = 'Email';

    const SUBSCRIBER_ID = 'Magento Subscriber ID';

    const CUSTOMER_ID = 'Magento Customer ID';

    const CUSTOMER_UNIQUE_ID = 'Magento Customer Unique ID';

    const OPT_IN = 'Opt-In';

    const BATCH_SIZE = 1000;

    const LOG_MESSAGE_SUBSCRIBER = 'Created Subscriber in Emarsys';

    const LOG_MESSAGE_CUSTOMER = 'Created Customer in Emarsys';

    /**
     * @var EmarsysModelLogs
     */
    protected $emarsysLogs;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var Timezone
     */
    protected $timezone;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadataInterface;

    /**
     * @var EmarsysHelperLogs
     */
    protected $logsHelper;

    /**
     * @var Customer
     */
    protected $customerResourceModel;

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * @var EmarsysQueueFactory
     */
    protected $queueColl;

    /**
     * @var CollectionFactory
     */
    protected $magentoEventsCollection;

    /**
     * @var PlaceholdersFactory
     */
    protected $emarsysEventPlaceholderMappingFactory;

    /**
     * @var ControllerTemplate
     */
    protected $emailTemplate;

    /**
     * @var ProductCollectionFactory
     */
    protected $magentoProductAttributeColl;

    /**
     * @var EmarsysEventMappingFactory
     */
    protected $emarsysEventMapping;

    /**
     * @var EmarsyseventsCollectionFactory
     */
    protected $emarsysEventCollectonFactory;

    /**
     * @var Event
     */
    protected $eventsResourceModel;

    /**
     * @var Emarsysevents
     */
    protected $emarsysEventsModel;

    /**
     * @var BackendSession
     */
    protected $session;

    /**
     * @var EmailTemplateFactory
     */
    protected $templateFactory;

    /**
     * @var EmarsyseventsFactory
     */
    protected $emarsysEventsModelFactory;

    /**
     * @var EmarsysApiApi
     */
    protected $api;

    /**
     * @var StoreCollectionFactory
     */
    protected $storeCollection;

    /**
     * @var ModuleListInterface
     */
    protected $moduleListInterface;

    /**
     * @var SubscriberCollectionFactory
     */
    protected $newsLetterCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var
     */
    private $_username;

    /**
     * @var
     */
    private $_secret;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var FilesystemIoFile
     */
    protected $filesystemIoFile;

    /**
     * @var Ftp
     */
    protected $ftp;

    /**
     * @var null
     */
    protected $websiteId = null;

    /**
     * @var string
     */
    protected $_emarsysApiUrl = 'https://trunk-int.s.emarsys.com/api/v2/';

    /**
     * @var array
     */
    protected $_websites = [];

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param DateTime $date
     * @param Timezone $timezone
     * @param EmarsysHelperLogs $logsHelper
     * @param StoreManagerInterface $storeManager
     * @param ModelResourceModelCustomer $customerResourceModel
     * @param Queue $queueModel
     * @param ProductMetadataInterface $productMetadataInterface
     * @param EmarsysQueueFactory $queueModelColl
     * @param CollectionFactory $magentoEventsCollection
     * @param PlaceholdersFactory $emarsysEventPlaceholderMappingFactory
     * @param ControllerTemplate $emailTemplate
     * @param ProductCollectionFactory $magentoProductAttributeColl
     * @param EmarsysEventMappingFactory $emarsysEventMapping
     * @param EmarsyseventsCollectionFactory $emarsysEventCollectionFactory
     * @param Emarsysevents $emarsysEventsModel
     * @param ModelResourceModelEvent $eventsResourceModel
     * @param EmailTemplateFactory $templateFactory
     * @param BackendSession $session
     * @param EmarsyseventsFactory $emarsysEventsModelFactory
     * @param EmarsysApiApi $api
     * @param EmarsysModelLogs $emarsysLogs
     * @param StoreCollectionFactory $storeCollection
     * @param ModuleListInterface $moduleListInterface
     * @param SubscriberCollectionFactory $newsLetterCollectionFactory
     * @param Ftp $ftp
     */
    public function __construct(
        Context $context,
        DateTime $date,
        Timezone $timezone,
        EmarsysHelperLogs $logsHelper,
        StoreManagerInterface $storeManager,
        ModelResourceModelCustomer $customerResourceModel,
        Queue $queueModel,
        ProductMetadataInterface $productMetadataInterface,
        EmarsysQueueFactory $queueModelColl,
        CollectionFactory $magentoEventsCollection,
        PlaceholdersFactory $emarsysEventPlaceholderMappingFactory,
        ControllerTemplate $emailTemplate,
        ProductCollectionFactory $magentoProductAttributeColl,
        EmarsysEventMappingFactory $emarsysEventMapping,
        EmarsyseventsCollectionFactory $emarsysEventCollectionFactory,
        Emarsysevents $emarsysEventsModel,
        ModelResourceModelEvent $eventsResourceModel,
        EmailTemplateFactory $templateFactory,
        BackendSession $session,
        EmarsyseventsFactory $emarsysEventsModelFactory,
        EmarsysApiApi $api,
        EmarsysModelLogs $emarsysLogs,
        StoreCollectionFactory $storeCollection,
        ModuleListInterface $moduleListInterface,
        SubscriberCollectionFactory $newsLetterCollectionFactory,
        DirectoryList $directoryList,
        FilesystemIoFile $filesystemIoFile,
        Ftp $ftp
    ) {
        $this->emarsysLogs = $emarsysLogs;
        $this->date = $date;
        $this->timezone = $timezone;
        $this->productMetadataInterface = $productMetadataInterface;
        $this->storeManager = $storeManager;
        $this->logsHelper = $logsHelper;
        $this->customerResourceModel = $customerResourceModel;
        $this->queue = $queueModel;
        $this->queueColl = $queueModelColl;
        $this->scopeConfigInterface = $context->getScopeConfig();
        $this->magentoEventsCollection = $magentoEventsCollection;
        $this->emarsysEventPlaceholderMappingFactory = $emarsysEventPlaceholderMappingFactory;
        $this->emailTemplate = $emailTemplate;
        $this->magentoProductAttributeColl = $magentoProductAttributeColl;
        $this->emarsysEventMapping = $emarsysEventMapping;
        $this->emarsysEventCollectonFactory = $emarsysEventCollectionFactory;
        $this->eventsResourceModel = $eventsResourceModel;
        $this->emarsysEventsModel = $emarsysEventsModel;
        $this->session = $session;
        $this->templateFactory = $templateFactory;
        $this->emarsysEventsModelFactory = $emarsysEventsModelFactory;
        $this->api = $api;
        $this->storeCollection = $storeCollection;
        $this->moduleListInterface = $moduleListInterface;
        $this->newsLetterCollectionFactory = $newsLetterCollectionFactory;
        $this->directoryList = $directoryList;
        $this->filesystemIoFile = $filesystemIoFile;
        $this->ftp = $ftp;

        parent::__construct($context);
    }

    /**
     * @param null|int $storeId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isTestModeEnabled($storeId = null)
    {
        return (bool)$this->storeManager->getStore($storeId)->getConfig(self::XPATH_WEBEXTEND_MODE);
    }

    /**
     * @param null|int $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    public function getUniqueIdentifier($storeId = null)
    {
        return $this->storeManager->getStore($storeId)->getConfig(self::XPATH_WEBEXTEND_UNIQUE_ID);
    }

    /**
     * @param null|int $storeId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isUseBaseCurrency($storeId = null)
    {
        return (bool)$this->storeManager->getStore($storeId)->getConfig(self::XPATH_WEBEXTEND_USE_BASE_CURRENCY);
    }

    /**
     * @param null|int $storeId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isIncludeTax($storeId = null)
    {
        return (bool)$this->storeManager->getStore($storeId)->getConfig(self::XPATH_WEBEXTEND_INCLUDE_TAX);
    }

    /**
     * @param null|int $storeId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isAsyncEnabled($storeId = null)
    {
        return (bool)$this->storeManager->getStore($storeId)->getConfig(self::XPATH_EMARSYS_ASYNC_ENABLE);
    }

    /**
     * Smart Insight API url
     *
     * @return mixed
     */
    public function getEmarsysApiUrl()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_EMARSYS_SIEXPORT_API_URL,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Smart Insight Order API url key
     *
     * @return mixed
     */
    public function getOrderApiUrlKey()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_EMARSYS_SIEXPORT_API_URL_KEY,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Smart Insight Product API url key
     *
     * @return mixed
     */
    public function getProductApiUrlKey()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_EMARSYS_CATALOG_EXPORT_API_URL_KEY,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Emarsys API Details
     *
     * @param int $storeId
     * @throws NoSuchEntityException
     */
    public function getEmarsysAPIDetails($storeId)
    {
        $store = $this->storeManager->getStore($storeId);
        $username = $store->getConfig(self::XPATH_EMARSYS_API_USER);
        $password = $this->_secret = $store->getConfig(self::XPATH_EMARSYS_API_PASS);
        $endpoint = $store->getConfig(self::XPATH_EMARSYS_API_ENDPOINT);

        if ($endpoint == 'custom') {
            $url = $store->getConfig(self::XPATH_EMARSYS_CUSTOM_URL);
            $this->_emarsysApiUrl = rtrim($url, '/') . "/";
        } elseif ($endpoint == 'cdn') {
            $this->_emarsysApiUrl = self::EMARSYS_CDN_API_URL;
        } else {
            $this->_emarsysApiUrl = self::EMARSYS_DEFAULT_API_URL;
        }

        $this->_username = $username;
        $this->_secret = $password;
    }

    /**
     * @param $storeId
     * @throws \Exception
     */
    public function insertFirstTime($storeId)
    {
        $magentoEvents = $this->magentoEventsCollection->create();

        foreach ($magentoEvents as $magentoEvent) {
            $eventMappingModel = $this->emarsysEventMapping->create();
            $eventMappingModel->setMagentoEventId($magentoEvent->getId());
            $eventMappingModel->setEmarsysEventId(0);
            $eventMappingModel->setStoreId($storeId);
            $eventMappingModel->save();
        }
    }

    /**
     * @return array
     */
    public function getReadonlyMagentoEventIds()
    {
        return [1, 2];
    }

    /**
     * Checking readonly magento events
     *
     * @param int $id
     * @return bool
     */
    public function isReadonlyMagentoEventId($id = 0)
    {
        if (in_array($id, $this->getReadonlyMagentoEventIds())) {
            return true;
        }

        return false;
    }

    /**
     * @param int|null $storeId
     * @param null $logId
     * @throws \Exception
     */
    public function importEvents($storeId = null, $logId = null)
    {
        $logsArray['id'] = $logId;
        $logsArray['emarsys_info'] = 'Update Schema';

        try {
            if (!$storeId && $this->session->getStoreId()) {
                $storeId = $this->session->getStoreId();
            }
            //get emarsys events and store it into array
            $eventArray = $this->getEvents($storeId, $logId);

            if (!count($eventArray)) {
                return;
            }

            //Delete unwanted events exist in database
            $emarsysEvents = $this->emarsysEventCollectonFactory->create();
            foreach ($emarsysEvents as $emarsysEvent) {
                if (!array_key_exists($emarsysEvent->getEventId(), $eventArray)) {
                    $this->eventsResourceModel->deleteEvent($emarsysEvent->getEventId(), $storeId);
                    $this->eventsResourceModel->deleteEventMapping($emarsysEvent->getId(), $storeId);
                }
            }
            //Update & create new events found in Emarsys
            foreach ($eventArray as $key => $value) {
                $emarsysEvents = $this->emarsysEventCollectonFactory->create();
                $emarsysEventItem = $emarsysEvents->addFieldToFilter("event_id", $key)
                    ->addFieldToFilter("store_id", $storeId)
                    ->getFirstItem();
                if ($emarsysEventItem && $emarsysEventItem->getId()) {
                    $model = $this->emarsysEventsModel->load($emarsysEventItem->getId(), "id");
                    $model->setEmarsysEvent($value);
                    $model->setStoreId($storeId);
                    $model->save();
                } else {
                    $model = $this->emarsysEventsModelFactory->create();
                    $model->setEventId($key);
                    $model->setEmarsysEvent($value);
                    $model->setStoreId($storeId);
                    $model->save();
                }
            }
        } catch (\Exception $e) {
            $logsArray['description'] = $e->getMessage();
            $logsArray['action'] = 'Update Schema';
            $logsArray['store_id'] = $storeId;
            $logsArray['message_type'] = 'Error';
            $logsArray['log_action'] = 'True';
            $logsArray['website_id'] = 0;
            $this->logsHelper->manualLogs($logsArray);

            return;
        }
    }

    /**
     * @param $storeId
     * @param null $logId
     * @return array
     * @throws \Exception
     */
    public function getEvents($storeId, $logId = null)
    {
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
        $logsArray['id'] = $logId;
        $logsArray['store_id'] = $storeId;
        $logsArray['emarsys_info'] = 'Update Schema';
        $result = [];
        try {
            $this->api->setWebsiteId($websiteId);
            $response = $this->api->sendRequest('GET', 'event');

            if ($response['status'] == 200) {
                if (isset($response['body']['data'])) {
                    foreach ($response['body']['data'] as $item) {
                        $result[$item['id']] = $item['name'];
                    }
                    $logsArray['description'] = Zend_Json::encode($result);
                    $logsArray['action'] = 'Update Schema';
                    $logsArray['message_type'] = 'Success';
                    $logsArray['log_action'] = 'True';
                    $logsArray['website_id'] = $websiteId;
                    $this->logsHelper->manualLogs($logsArray);
                }
            } else {
                $logsArray['description'] = Zend_Json::encode($response);
                $logsArray['action'] = 'Update Schema';
                $logsArray['message_type'] = 'Error';
                $logsArray['log_action'] = 'True';
                $logsArray['website_id'] = $websiteId;
                $this->logsHelper->manualLogs($logsArray);
            }
        } catch (\Exception $e) {
            $logsArray['description'] = $e->getMessage();
            $logsArray['action'] = 'Mail Sent';
            $logsArray['message_type'] = 'Error';
            $logsArray['log_action'] = 'True';
            $logsArray['website_id'] = $websiteId;

            $this->logsHelper->manualLogs($logsArray);
        }

        return $result;
    }

    /**
     * @return array|mixed
     */
    public function getPhpInfoArray()
    {
        try {
            ob_start();
            phpinfo(INFO_ALL);

            $pi = preg_replace(
                [
                    '#^.*<body>(.*)</body>.*$#m',
                    '#<h2>PHP License</h2>.*$#ms',
                    '#<h1>Configuration</h1>#',
                    "#\r?\n#",
                    "#</(h1|h2|h3|tr)>#",
                    '# +<#',
                    "#[ \t]+#",
                    '#&nbsp;#',
                    '#  +#',
                    '# class=".*?"#',
                    '%&#039;%',
                    '#<tr>(?:.*?)" src="(?:.*?)=(.*?)" alt="PHP Logo" /></a><h1>PHP Version (.*?)</h1>(?:\n+?)</td></tr>#',
                    '#<h1><a href="(?:.*?)\?=(.*?)">PHP Credits</a></h1>#',
                    '#<tr>(?:.*?)" src="(?:.*?)=(.*?)"(?:.*?)Zend Engine (.*?),(?:.*?)</tr>#',
                    "# +#",
                    '#<tr>#',
                    '#</tr>#',
                ],
                [
                    '$1',
                    '',
                    '',
                    '',
                    '</$1>' . "\n",
                    '<',
                    ' ',
                    ' ',
                    ' ',
                    '',
                    ' ',
                    '<h2>PHP Configuration</h2>' . "\n" . '<tr><td>PHP Version</td><td>$2</td></tr>' .
                    "\n" . '<tr><td>PHP Egg</td><td>$1</td></tr>',
                    '<tr><td>PHP Credits Egg</td><td>$1</td></tr>',
                    '<tr><td>Zend Engine</td><td>$2</td></tr>' . "\n" .
                    '<tr><td>Zend Egg</td><td>$1</td></tr>',
                    ' ',
                    '%S%',
                    '%E%',
                ],
                ob_get_clean()
            );

            $sections = explode('<h2>', strip_tags($pi, '<h2><th><td>'));
            unset($sections[0]);

            $pi = [];
            foreach ($sections as $section) {
                $n = substr($section, 0, strpos($section, '</h2>'));
                preg_match_all(
                    '#%S%(?:<td>(.*?)</td>)?(?:<td>(.*?)</td>)?(?:<td>(.*?)</td>)?%E%#',
                    $section,
                    $askapache,
                    PREG_SET_ORDER
                );
                foreach ($askapache as $m) {
                    if (!isset($m[0]) || !isset($m[1]) || !isset($m[2])) {
                        continue;
                    }
                    $pi[$n][$m[1]] = (!isset($m[3]) || $m[2] == $m[3]) ? $m[2] : array_slice($m, 2);
                }
            }
        } catch (\Exception $exception) {
            return [];
        }

        return $pi;
    }

    /**
     * @return array
     */
    public function getPhpSettings()
    {
        return [
            'max_execution_time' => ini_get('max_execution_time'),
            'phpinfo' => $this->getPhpInfoArray(),
        ];
    }

    /**
     * @return mixed
     */
    public function getPhpVersion()
    {
        $phpVersion = phpversion();
        $array = explode("-", $phpVersion);
        return $array[0];
    }

    /**
     * @param bool $asArray
     * @return string
     */
    public function getVersion($asArray = false)
    {
        $version = '';

        if ($this->productMetadataInterface->getVersion()) {
            $version = $this->productMetadataInterface->getVersion();
        }

        return $version;
    }

    /**
     * @param null $storeId
     * @return array
     * @throws NoSuchEntityException
     */
    public function getRequirementsInfo($storeId = null)
    {
        if (!$storeId) {
            $storeId = $this->storeManager->getStore()->getId();
        }
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
        $aggregatePortStatus = $this->openPortStatus($storeId);
        $smartInsightStatus = $this->getCheckSmartInsight($websiteId);
        $smartInsight = $smartInsightStatus ? 'Enable' : 'Disable';
        $emarsysStatus = $this->getEmarsysConnectionSetting($websiteId);
        $emarsysModuleStatus = $emarsysStatus ? 'Enable' : 'Disable';

        $clientPhpData = $this->getPhpSettings();

        if ($clientPhpData['phpinfo']['soap'] ['Soap Client'] == 'enabled') {
            $soapenable = 'Yes';
        } else {
            $soapenable = 'No';
        }

        if ($clientPhpData['phpinfo']['curl'] ['cURL support'] == 'enabled') {
            $curlenable = 'Yes';
        } else {
            $curlenable = 'No';
        }

        $requirements = [
            'php_version' => [
                'title' => 'PHP Version',
                'condition' => [
                    'sign' => '>=',
                    'value' => '5.6.0',
                ],
                'current' => [
                    'value' => $this->getPhpVersion(),
                    'status' => true,
                ],
            ],
            'memory_limit' => [
                'title' => 'Memory Limit',
                'condition' => [
                    'sign' => '>=',
                    'value' => '512 MB',
                ],
                'current' => [
                    'value' => ini_get('memory_limit'),
                    'status' => true,
                ],
            ],
            'magento_version' => [
                'title' => 'Magento Version',
                'condition' => [
                    'sign' => '>=',
                    'value' => '2.1',
                ],
                'current' => [
                    'value' => $this->getVersion(false),
                    'status' => true,
                ],
            ],
            'emarsys_extension_version' => [
                'title' => 'Emarsys Extension Version',
                'condition' => [
                    'sign' => '>=',
                    'value' => self::VERSION,
                ],
                'current' => [
                    'value' => $this->getEmarsysVersion(),
                    'status' => true,
                ],
            ],
            'curl_enabled' => [
                'title' => 'Curl Enabled',
                'condition' => [
                    'sign' => '',
                    'value' => 'Yes',
                ],
                'current' => [
                    'value' => $curlenable,
                    'status' => true,
                ],
            ],
            'tcp_21' => [
                'title' => 'TCP21,TCP32000-35000Open',
                'condition' => [
                    'sign' => '',
                    'value' => 'Yes',
                ],
                'current' => [
                    'value' => $aggregatePortStatus,
                    'status' => true,
                ],
            ],
            'soap_enabled' => [
                'title' => 'SOAP Enabled',
                'condition' => [
                    'sign' => '',
                    'value' => 'Yes',
                ],
                'current' => [
                    'value' => $soapenable,
                    'status' => true,
                ],
            ],
            'smart_insight' => [
                'title' => 'Smart Insight',
                'condition' => [
                    'sign' => '',
                    'value' => 'Enable',
                ],
                'current' => [
                    'value' => $smartInsight,
                    'status' => true,
                ],
            ],
            'real_time_sync' => [
                'title' => 'Realtime Synchronization',
                'condition' => [
                    'sign' => '',
                    'value' => 'Enable',
                ],
                'current' => [
                    'value' => 'Enable',
                    'status' => true,
                ],
            ],
            'emarsys_connection_setting' => [
                'title' => 'Emarsys Connection Setting',
                'condition' => [
                    'sign' => '',
                    'value' => 'Enable',
                ],
                'current' => [
                    'value' => $emarsysModuleStatus,
                    'status' => true,
                ],
            ],
        ];

        foreach ($requirements as $key => &$requirement) {
            // max execution time is unlimited
            if ($key == 'max_execution_time' && $clientPhpData['max_execution_time'] == 0) {
                continue;
            }

            $requirement['current']['status'] = version_compare(
                $requirement['current']['value'],
                $requirement['condition']['value'],
                $requirement['condition']['sign']
            );
        }

        return $requirements;
    }

    /**
     * @param $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    public function openPortStatus($storeId)
    {
        $host = $this->storeManager->getStore($storeId)->getConfig(self::XPATH_EMARSYS_FTP_HOSTNAME);

        $ports = [21, rand(32000, 32500), rand(32000, 32500)];
        $portStatus = [];
        foreach ($ports as $port) {
            $errno = null;
            $errstr = null;

            try {
                $connection = fsockopen($host, $port, $errno, $errstr);
                if (is_resource($connection)) {
                    $portStatus[] = 'open';
                    fclose($connection);
                } else {
                    $portStatus[] = 'closed';
                }
            } catch (\Exception $e) {
                $portStatus[] = 'closed';
            }
        }
        if (in_array('closed', $portStatus)) {
            $aggregatePortStatus = 'No';
        } else {
            $aggregatePortStatus = 'Yes';
        }

        return $aggregatePortStatus;
    }

    /**
     * @param $customerId
     * @param $websiteId
     * @param $storeId
     * @param $cron
     * @param $entityType
     * @throws \Exception
     */
    public function syncFail($customerId, $websiteId, $storeId, $cron, $entityType)
    {
        $queueColl = $this->queueColl->create()->getCollection();
        $queueModel = $this->queue;
        $queueColl->addFieldToFilter('entity_id', $customerId);
        $queueColl->addFieldToFilter('website_id', $websiteId);

        if ($queueColl->getSize()) {
            $data = $queueColl->getFirstItem();
            $queueModel->load($data->getId());
            if ($cron == 1) {
                $queueModel->setHitCount($data->getHitCount() + 1);
            } else {
                $queueModel->setHitCount(0);
            }
            $queueModel->save();
        } else {
            $queueModel = $this->queueColl->create();
            $queueModel->setEntityId($customerId);
            $queueModel->setWebsiteId($websiteId);
            $queueModel->setStoreId($storeId);
            $queueModel->setEntityTypeId($entityType);
            $queueModel->save();
        }
    }

    /**
     * @param $customerId
     * @param $websiteId
     * @param $storeId
     * @param $cron
     * @throws \Exception
     */
    public function syncSuccess($customerId, $websiteId, $storeId, $cron)
    {
        $queueColl = $this->queueColl->create()->getCollection();
        $queueModel = $this->queue;

        $queueColl->addFieldToFilter('entity_id', $customerId);
        $queueColl->addFieldToFilter('website_id', $websiteId);

        if ($queueColl->getSize() > 0) {
            $queueModel->load($queueColl->getFirstItem()->getId());
            $queueModel->delete();
        }
    }

    /**
     * @param $mappingId
     * @param $storeId
     * @return array
     * @throws \Exception
     */
    public function insertFirstTimeMappingPlaceholders($mappingId, $storeId)
    {
        $store = $this->storeManager->getStore($storeId);
        $return = [];
        $magentoPlaceholderArray = [];
        $value = '';
        $emarsysEventMappingColl = $this->emarsysEventMapping->create()->getCollection()
            ->addFieldToFilter('magento_event_id', $mappingId)
            ->addFieldToFilter('store_id', $storeId);
        $emarsysEventMappingItem = $emarsysEventMappingColl->getFirstItem();

        if ($emarsysEventMappingItem->getMagentoEventId()) {
            $magentoEventColl = $this->magentoEventsCollection->create()
                ->addFieldToFilter('id', $emarsysEventMappingItem->getMagentoEventId());
            $magentoEventItem = $magentoEventColl->getFirstItem();
            $value = $store->getConfig($magentoEventItem->getConfigPath());
        }

        if (empty($value)) {
            return $return;
        }
        if (is_numeric($value)) {
            $emailTemplateModelColl = $this->templateFactory->create()->getCollection()
                ->addFieldToFilter('template_id', $value);
            $emailText = $emailTemplateModelColl->getFirstItem()->getTemplateText();
        } else {
            $template = $this->emailTemplate->initTemplate('id');
            $template->setForcedArea($value);
            $template->loadDefault($value);
            $emailText = $template->getTemplateText();
        }

        while ($variable = $this->substringBetween($emailText)) {
            $emailText = str_replace($variable, '', $emailText);

            if (strstr($variable, '{{/depend')) {
                $variable = $this->substringBetweenTransVar($variable);
            }
            if (strstr($variable, '{{/if')) {
                $variable = $this->substringBetweenTransVar($variable);
            }
            if (strstr($variable, '{{template config_path')) {
                $variable = $this->substringBetweenTransVar($variable);
            }
            if (strstr($variable, '{{depend')) {
                $variable = $this->substringBetweenTransVar($variable);
            }
            if (strstr($variable, '{{if')) {
                $variable = $this->substringBetweenTransVar($variable);
            }
            if (strstr($variable, '{{else')) {
                $variable = $this->substringBetweenTransVar($variable);
            }
            if (strstr($variable, '{{inlinecss')) {
                $variable = $this->substringBetweenTransVar($variable);
            }
            if (strstr($variable, '{{css')) {
                $variable = $this->substringBetweenTransVar($variable);
            }

            if (!empty($variable)) {
                $name = $variable;
                if (strstr($name, '{{trans')) {
                    $name = $this->substringBetweenTransVar($name);
                }
                if (empty($name)) {
                    continue;
                }
                if (in_array($variable, $magentoPlaceholderArray)) {
                    continue;
                }
                $placeholderModel = $this->emarsysEventPlaceholderMappingFactory->create()->getCollection()
                    ->addFieldToFilter('event_mapping_id', $mappingId)
                    ->addFieldToFilter('store_id', $storeId)
                    ->addFieldToFilter('magento_placeholder_name', $variable)
                    ->getFirstItem();

                $placeholderModel->setEventMappingId($mappingId);
                $placeholderModel->setMagentoPlaceholderName($variable);
                $placeholderModel->setEmarsysPlaceholderName($this->getPlaceholderName($name));
                $placeholderModel->setStoreId($storeId);
                $magentoPlaceholderArray[] = $variable;
                $placeholderModel->save();
                $return[$placeholderModel->getEmarsysPlaceholderName()] = $variable;
            }
        }

        return $return;
    }

    /**
     * Refresh Placeholders
     *
     * @param $mappingId
     * @param $storeId
     * @throws \Magento\Framework\Exception\MailException
     * @throws NoSuchEntityException
     */
    public function refreshPlaceholders($mappingId, $storeId)
    {
        $store = $this->storeManager->getStore($storeId);
        $emarsysEventFromDbArray = [];
        $emarsysEventsMappingCollFromDb = $this->emarsysEventPlaceholderMappingFactory->create()
            ->getCollection()
            ->addFieldToFilter('event_mapping_id', $mappingId)
            ->addFieldToFilter('store_id', $storeId);

        foreach ($emarsysEventsMappingCollFromDb as $emarsysEventFromDb) {
            $emarsysEventFromDbArray[$emarsysEventFromDb->getId()] = $emarsysEventFromDb->getMagentoPlaceholderName();
        }

        $emarsysEventMappingColl = $this->emarsysEventMapping->create()
            ->getCollection()
            ->addFieldToFilter('magento_event_id', $mappingId)
            ->addFieldToFilter('store_id', $storeId);

        $emarsysEventMappingItem = $emarsysEventMappingColl->getFirstItem();

        $magentoEventColl = $this->magentoEventsCollection->create()
            ->addFieldToFilter('id', $emarsysEventMappingItem->getMagentoEventId());

        $magentoEventItem = $magentoEventColl->getFirstItem();

        $value = $store->getConfig($magentoEventItem->getConfigPath());

        if (is_numeric($value)) {
            $emailTemplateModelColl = $this->templateFactory->create()
                ->getCollection()
                ->addFieldToFilter('template_id', $value);
            $emailText = $emailTemplateModelColl->getFirstItem()->getTemplateText();
        } else {
            $template = $this->emailTemplate->initTemplate('id');
            $template->setForcedArea($value);
            $template->loadDefault($value);
            $emailText = $template->getTemplateText();
        }

        $templatePlaceholderArray = [];
        while ($variable = $this->substringBetween($emailText)) {
            $emailText = str_replace($variable, '', $emailText);

            if (strstr($variable, '{{/depend')) {
                $variable = $this->substringBetweenTransVar($variable);
            }
            if (strstr($variable, '{{/if')) {
                $variable = $this->substringBetweenTransVar($variable);
            }
            if (strstr($variable, '{{template config_path')) {
                $variable = $this->substringBetweenTransVar($variable);
            }
            if (strstr($variable, '{{depend')) {
                $variable = $this->substringBetweenTransVar($variable);
            }
            if (strstr($variable, '{{if')) {
                $variable = $this->substringBetweenTransVar($variable);
            }
            if (strstr($variable, '{{else')) {
                $variable = $this->substringBetweenTransVar($variable);
            }
            if (strstr($variable, '{{inlinecss')) {
                $variable = $this->substringBetweenTransVar($variable);
            }
            if (strstr($variable, '{{css')) {
                $variable = $this->substringBetweenTransVar($variable);
            }

            $templatePlaceholderArray[] = $variable;
        }

        $deleteFromDbArray = array_diff($emarsysEventFromDbArray, $templatePlaceholderArray);

        foreach ($deleteFromDbArray as $key => $_deleteFromDbArray) {
            $placeholderModel = $this->emarsysEventPlaceholderMappingFactory->create()->load($key);
            $placeholderModel->delete();
        }

        $insertNewFromTemplate = array_diff($templatePlaceholderArray, $emarsysEventFromDbArray);

        foreach ($insertNewFromTemplate as $key => $_insertNewFromTemplate) {
            if ($_insertNewFromTemplate) {
                $name = $_insertNewFromTemplate;
                if (strstr($name, '{{trans')) {
                    $name = $this->substringBetweenTransVar($name);
                }
                if (empty($name)) {
                    continue;
                }
                $placeholderModel = $this->emarsysEventPlaceholderMappingFactory->create();
                $placeholderModel->setEventMappingId($mappingId);
                $placeholderModel->setMagentoPlaceholderName($_insertNewFromTemplate);
                $placeholderModel->setEmarsysPlaceholderName($this->getPlaceholderName($name));
                $placeholderModel->setStoreId($storeId);
                $placeholderModel->save();
            }
        }
    }

    /**
     * @param $haystack
     * @param string $start
     * @param string $end
     * @return bool|string
     * @throws NoSuchEntityException
     */
    public function substringBetween($haystack, $start = "{{", $end = "}}")
    {
        try {
            if (strpos($haystack, $start) === false || strpos($haystack, $end) === false) {
                return false;
            } else {
                $start_position = strpos($haystack, $start) + strlen($start);
                $end_position = strpos($haystack, $end);
                $string = substr($haystack, $start_position, $end_position - $start_position);
                return $start . $string . $end;
            }
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog(
                $e->getMessage(),
                $this->storeManager->getStore()->getId(),
                'substringBetween($haystack, $start = "{{", $end = "}}")'
            );
        }
    }

    /**
     * @param $haystack
     * @param string $start
     * @param string $end
     * @return bool|string
     * @throws NoSuchEntityException
     */
    public function substringBetweenTransVar($haystack, $start = "=$", $end = "}}")
    {
        try {
            if (strpos($haystack, $start) === false || strpos($haystack, $end) === false) {
                return false;
            } else {
                $start_position = strpos($haystack, $start) + strlen($start);
                $end_position = strpos($haystack, $end);
                $string = substr($haystack, $start_position, $end_position - $start_position);
                return "{{var " . $string . "}}";
            }
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog(
                $e->getMessage(),
                $this->storeManager->getStore()->getId(),
                'substringBetweenTransVar($haystack, $start = "=$", $end = "}}")'
            );
        }
    }

    /**
     * @param string $variable
     * @return string
     * @throws NoSuchEntityException
     */
    public function getPlaceholderName($variable = '')
    {
        try {
            if (empty($variable)
                || in_array($variable, ["{{/if}}", "{{/depend}}", "{{else}}", "{{var non_inline_styles}}"])
                || strstr($variable, 'inlinecss')
            ) {
                return;
            }
            $findReplace = [
                " " => "_",
                ".get" => "_",
                "." => "_",
                "{{" => "_",
                "}}" => "_",
                "()" => "_",
                "(" => "_",
                ")" => "_",
                "[" => "_",
                "]" => "_",
                "<" => "_",
                ">" => "_",
                "=$" => "_",
                "=" => "_",
                "/" => "_",
                "\\" => "_",
                "\n" => "_",
                "$" => "_",
                "%" => "_",
                "," => "_",
                ":" => "_",
                "|" => "_",
                "'" => "",
                '"' => "",
                "var" => "",
                "trans" => "",
                "_____" => "_",
                "____" => "_",
                "___" => "_",
                "__" => "_",
                "#" => '',
            ];
            $emarsysVariable = str_replace(array_keys($findReplace), $findReplace, strtolower($variable));
            return trim(trim($emarsysVariable, "_"));
        } catch (\Exception $e) {
            $storeId = $this->storeManager->getStore()->getId();
            $this->emarsysLogs->addErrorLog($e->getMessage(), $storeId, 'getPlaceholderName');
        }
    }

    /**
     * @return array
     */
    public function emarsysDefaultPlaceholders()
    {
        $order['product_purchases']['unitary_price_exc_tax'] = strtoupper("unitary_price_exc_tax");
        $order['product_purchases']['unitary_price_inc_tax'] = strtoupper("unitary_price_inc_tax");
        $order['product_purchases']['unitary_tax_amount'] = strtoupper("unitary_tax_amount");
        $order['product_purchases']['line_total_price_exc_tax'] = strtoupper("line_total_price_exc_tax");
        $order['product_purchases']['line_total_tax_amount'] = strtoupper("line_total_tax_amount");
        $order['product_purchases']['unitary_price_inc_tax'] = strtoupper("unitary_price_inc_tax");
        $order['product_purchases']['product_id'] = strtoupper("product_id");
        $order['product_purchases']['product_type'] = strtoupper("product_type");
        $order['product_purchases']['base_original_price'] = strtoupper("base_original_price");
        $order['product_purchases']['sku'] = strtoupper("sku");
        $order['product_purchases']['product_name'] = strtoupper("product_name");
        $order['product_purchases']['product_weight'] = strtoupper("product_weight");
        $order['product_purchases']['qty_ordered'] = strtoupper("qty_ordered");
        $order['product_purchases']['original_price'] = strtoupper("original_price");
        $order['product_purchases']['price'] = strtoupper("price");
        $order['product_purchases']['base_price'] = strtoupper("base_price");
        $order['product_purchases']['tax_percent'] = strtoupper("tax_percent");
        $order['product_purchases']['tax_amount'] = strtoupper("tax_amount");
        $order['product_purchases']['discount_amount'] = strtoupper("discount_amount");
        $order['product_purchases']['price_line_total'] = strtoupper("price_line_total");
        $order['product_purchases']['_external_image_url'] = strtoupper("_external_image_url");
        $order['product_purchases']['_url'] = strtoupper("_url");
        $order['product_purchases']['_url_name'] = strtoupper("_url_name");
        $order['product_purchases']['product_description'] = strtoupper("product_description");
        $order['product_purchases']['short_description'] = strtoupper("short_description");
        $order['product_purchases']['additional_data'] = strtoupper("additional_data");
        $order['product_purchases']['full_options'] = strtoupper("full_options");

        $attributesColl = $this->magentoProductAttributeColl->create();

        foreach ($attributesColl as $attribute) {
            if ($attribute['attribute_code'] == "gallery") {
                continue;
            }
            $order['product_purchases']['attribute_' . $attribute['attribute_code']] = strtoupper(
                'attribute_' . $attribute['attribute_code']
            );
        }

        return $order;
    }

    /**
     * @param $storeId
     * @return array
     */
    public function insertFirstTimeHeaderMappingPlaceholders($storeId)
    {
        try {
            $magentoEventsCollection = $this->magentoEventsCollection->create()
                ->addFieldToFilter('config_path', 'design/email/header_template');
            $emarsysEventMappingColl = $this->emarsysEventMapping->create()
                ->getCollection()
                ->addFieldToFilter('magento_event_id', $magentoEventsCollection->getFirstItem()->getId())
                ->addFieldToFilter('store_id', $storeId);

            return $this->insertFirstTimeMappingPlaceholders(
                $emarsysEventMappingColl->getFirstItem()->getMagentoEventId(),
                $storeId
            );
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog(
                htmlentities($e->getMessage()),
                $storeId,
                'insertFirstTimeHeaderMappingPlaceholders'
            );
        }
        return [];
    }

    /**
     * @param $storeId
     * @return array
     */
    public function insertFirstTimeFooterMappingPlaceholders($storeId)
    {
        try {
            $magentoEventsCollection = $this->magentoEventsCollection->create()
                ->addFieldToFilter('config_path', 'design/email/footer_template');
            $emarsysEventMappingColl = $this->emarsysEventMapping->create()
                ->getCollection()
                ->addFieldToFilter(
                    'magento_event_id',
                    $magentoEventsCollection->getFirstItem()->getId()
                )
                ->addFieldToFilter('store_id', $storeId);

            return $this->insertFirstTimeMappingPlaceholders(
                $emarsysEventMappingColl->getFirstItem()->getMagentoEventId(),
                $storeId
            );
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog(
                htmlentities($e->getMessage()),
                $storeId,
                'insertFirstTimeFooterMappingPlaceholders'
            );
        }
        return [];
    }

    /**
     * @param $storeId
     * @return array
     */
    public function emarsysHeaderPlaceholders($storeId)
    {
        $headerPlaceholderArray = [];
        try {
            $magentoEventsCollection = $this->magentoEventsCollection->create()
                ->addFieldToFilter('config_path', 'design/email/header_template');
            $placeholderMappingColls = $this->emarsysEventPlaceholderMappingFactory->create()
                ->getCollection()
                ->addFieldToFilter('event_mapping_id', $magentoEventsCollection->getFirstItem()->getId());

            foreach ($placeholderMappingColls as $coll) {
                $headerPlaceholderArray[$coll->getEmarsysPlaceholderName()] = $coll->getMagentoPlaceholderName();
            }

            return $headerPlaceholderArray;
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog(
                htmlentities($e->getMessage()),
                $storeId,
                'emarsysHeaderPlaceholders'
            );
        }

        return $headerPlaceholderArray;
    }

    /**
     * @param $storeId
     * @return array
     */
    public function emarsysFooterPlaceholders($storeId)
    {
        $footer = [];
        try {
            $magentoEventsCollection = $this->magentoEventsCollection->create()
                ->addFieldToFilter('config_path', 'design/email/footer_template');
            $emarsysEventPlaceholders = $this->emarsysEventPlaceholderMappingFactory->create()
                ->getCollection()
                ->addFieldToFilter('event_mapping_id', $magentoEventsCollection->getFirstItem()->getId());

            foreach ($emarsysEventPlaceholders as $placeholder) {
                $footer[$placeholder->getEmarsysPlaceholderName()] = $placeholder->getMagentoPlaceholderName();
            }
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog(
                htmlentities($e->getMessage()),
                $storeId,
                'emarsysFooterPlaceholders'
            );
        }

        return $footer;
    }

    /**
     * @param $websiteId
     * @return bool
     */
    public function getCheckSmartInsight($websiteId = null)
    {
        $smartInsight = false;

        if ($this->isEmarsysEnabled($websiteId)) {
            /** @var $website Website */
            $website = $this->storeManager->getWebsite($websiteId);
            $smartInsight = $website->getConfig(self::XPATH_SMARTINSIGHT_ENABLED);
        }

        return (bool)$smartInsight;
    }

    /**
     * @param $websiteId
     * @return bool
     */
    public function getEmarsysConnectionSetting($websiteId)
    {
        $result = false;

        if ($websiteId) {
            if ($this->scopeConfig->getValue(
                self::XPATH_EMARSYS_ENABLED,
                \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES,
                $websiteId
            )) {
                $result = true;
            }
        } else {
            if ($this->scopeConfig->getValue(self::XPATH_EMARSYS_ENABLED)) {
                $result = true;
            }
        }

        return $result;
    }

    /**
     * @param $templateId
     * @param $storeId
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getMagentoEventId($templateId, $storeId = null)
    {
        $store = $this->storeManager->getStore($storeId);
        $magentoEventsCollection = $this->magentoEventsCollection->create();
        foreach ($magentoEventsCollection as $magentoEvent) {
            if ($store->getConfig($magentoEvent->getConfigPath()) == $templateId
                || $magentoEvent->getTemplateId() == $templateId
            ) {
                return $magentoEvent->getId();
            }
        }

        try {
            $magentoEvent = clone $magentoEvent;
            $magentoEvent->setData([]);
            $magentoEvent->setData(
                [
                    'magento_event' => ucwords(str_ireplace('_', ' ', $templateId)),
                    'template_id' => $templateId
                ]
            );
            $magentoEvent->save();
        } catch (\Exception $e) {
            return null;
        }

        return $magentoEvent->getId();
    }

    /**
     * @param $magentoEventId
     * @param null $storeId
     * @return mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getEmarsysEventApiId($magentoEventId, $storeId = null)
    {
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        $emarsysEventId = $this->getEmarsysEventId($magentoEventId, $storeId);

        if (!$emarsysEventId) {
            $nStoreId = $this->getFirstStoreIdOfWebsiteByStoreId($storeId);
            if ($nStoreId != $storeId) {
                $emarsysEventId = $this->getEmarsysEventId($magentoEventId, $storeId);
            }
        }

        return $this->emarsysEventsModelFactory->create()
            ->getCollection()
            ->addFieldToFilter('id', $emarsysEventId)
            ->getFirstItem()
            ->getEventId();
    }

    /**
     * @param $magentoEventId
     * @param null $storeId
     * @return mixed
     */
    public function getEmarsysEventId($magentoEventId, $storeId)
    {
        return $this->emarsysEventMapping->create()
            ->getCollection()
            ->addFieldToFilter('store_id', $storeId)
            ->addFieldToFilter('magento_event_id', $magentoEventId)
            ->getFirstItem()
            ->getEmarsysEventId();
    }

    /**
     * @param $emarsysEventMappingId
     * @param $storeId
     * @return array
     * @throws NoSuchEntityException
     */
    public function getPlaceHolders($emarsysEventMappingId, $storeId)
    {
        $placeHolders = [];
        try {
            $emarsysPlaceholderCollection = $this->emarsysEventPlaceholderMappingFactory->create()
                ->getCollection()
                ->addFieldToFilter('event_mapping_id', $emarsysEventMappingId)
                ->addFieldToFilter('store_id', $storeId);

            if ($emarsysPlaceholderCollection->getSize()) {
                foreach ($emarsysPlaceholderCollection as $value) {
                    $placeHolders[$value->getEmarsysPlaceholderName()] = $value->getMagentoPlaceholderName();
                }
            }
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog(
                htmlentities($e->getMessage()),
                $this->storeManager->getStore()->getId(),
                'getPlaceHolders'
            );
        }

        return $placeHolders;
    }

    /**
     * @param $path
     * @param $storeCode
     * @param $storeId
     * @return mixed
     */
    public function getConfigValue($path, $storeCode, $storeId)
    {
        if ($storeCode == "default") {
            return $this->scopeConfig->getValue($path, 'default', 0);
        } else {
            return $this->scopeConfig->getValue($path, $storeCode, $storeId);
        }
    }

    /**
     * Technical support email for error logs
     *
     * @return array
     */
    public function logErrorSenderEmail()
    {
        $data = [
            'name' => 'Technical Support',
            'email' => 'support@emarsys.com',
        ];
        return $data;
    }

    /**
     * @return int
     */
    public function getFirstStoreId()
    {
        $stores = $this->storeManager->getStores();
        ksort($stores);

        $firstStore = false;
        foreach ($stores as $store) {
            if ($store->getConfig(self::XPATH_EMARSYS_ENABLED)) {
                $firstStore = $store;
                break;
            }
        }

        if ($firstStore) {
            $store = $firstStore;
        } else {
            $store = reset($stores);
        }

        /** @var \Magento\Store\Model\Website $website */
        $website = $store->getWebsite();
        $defaultStore = $website->getDefaultStore() ?? false;
        if ($defaultStore && $defaultStore->getId()) {
            $firstStoreId = $defaultStore->getId();
        } else {
            $firstStoreId = $store->getId();
        }

        return $firstStoreId;
    }

    /**
     * @param $websiteId
     * @return int
     * @throws LocalizedException
     */
    public function getFirstStoreIdOfWebsite($websiteId)
    {
        $websites = $this->storeManager->getWebsites();
        ksort($websites);

        foreach ($websites as $wId => $website) {
            if ($website->getConfig(self::XPATH_EMARSYS_ENABLED)) {
                $apiUserName = $website->getConfig(self::XPATH_EMARSYS_API_USER);
                if (!isset($this->_websites[$apiUserName])) {
                    $this->_websites[$apiUserName] = $wId;
                }

                if ($wId == $websiteId) {
                    $websiteId = $this->_websites[$apiUserName];
                    break;
                }
            }
        }
        /** @var WebsiteInterface $websiteId */
        $website = $this->storeManager->getWebsite($websiteId);

        $defaultStore = $website->getDefaultStore();
        if (($defaultStore ?? null) && $defaultStore->getId()) {
            $firstStoreId = $defaultStore->getId();
        } else {
            $stores = $website->getStores();
            ksort($stores);
            $store = reset($stores);
            $firstStoreId = $store->getId();
        }

        return $firstStoreId;
    }

    /**
     * @param $storeId
     * @return int
     * @throws LocalizedException
     */
    public function getFirstStoreIdOfWebsiteByStoreId($storeId)
    {
        if (!$storeId) {
            return $this->getFirstStoreId();
        }

        $store = $this->storeManager->getStore($storeId);

        return $this->getFirstStoreIdOfWebsite($store->getWebsiteId());
    }

    /**
     * @param $templateId
     * @param $storeScope
     * @param null $storeId
     * @return array
     * @throws NoSuchEntityException
     */
    public function getMagentoEventIdAndPath($templateId, $storeScope, $storeId = null)
    {
        $event_id = "";
        $configPath = '';
        try {
            $magentoEventsCollection = $this->magentoEventsCollection->create();

            foreach ($magentoEventsCollection as $magentoEvent) {
                $configTemplateId = $this->scopeConfig->getValue($magentoEvent->getConfigPath(), $storeScope, $storeId);
                if ($configTemplateId == $templateId) {
                    $event_id = $magentoEvent->getId();
                    $configPath = $magentoEvent->getConfigPath();
                }
            }
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog(
                htmlentities($e->getMessage()),
                $this->storeManager->getStore()->getId(),
                'getMagentoEventIdAndPath'
            );
        }

        return [$event_id, $configPath];
    }

    /**
     * @param string $dateTime
     * @return \DateTime|string
     */
    public function getDateTimeInLocalTimezone($dateTime = '')
    {
        $toTimezone = $this->timezone->getConfigTimezone();
        $returnDateTime = $this->timezone->date($dateTime);
        $returnDateTime->setTimezone(new \DateTimeZone($toTimezone));
        $returnDateTime = $returnDateTime->format('Y-m-d H:i:s');

        return $returnDateTime;
    }

    /**
     * Checks whether emarsys is enabled or not.
     *
     * @param null $websiteId
     * @return boolean
     * @throws LocalizedException
     */
    public function isEmarsysEnabled($websiteId = null)
    {
        return (bool)$this->storeManager->getWebsite($websiteId)->getConfig(self::XPATH_EMARSYS_ENABLED);
    }

    /**
     * Checks whether contacts synchronization is enabled or not.
     *
     * @param null $websiteId
     * @return bool
     * @throws LocalizedException
     */
    public function isContactsSynchronizationEnable($websiteId = null)
    {
        $contactsSynchronization = false;

        if ($this->isEmarsysEnabled($websiteId)) {
            /** @var Website $website */
            $website = $this->storeManager->getWebsite($websiteId);
            $contactsSynchronization = $website->getConfig(self::XPATH_EMARSYS_ENABLE_CONTACT_FEED);
        }

        return (bool)$contactsSynchronization;
    }

    /**
     * @return mixed
     */
    public function getEmarsysVersion()
    {
        return $this->moduleListInterface->getOne('Emarsys_Emarsys')['setup_version'];
    }

    /**
     * @param $subscriber
     * @param $logMessage
     * @return bool
     */
    public function realtimeTimeBasedOptinSync($subscriber, $logMessage = 'Created Subscriber in Emarsys')
    {
        try {
            $sId = $this->getFirstStoreIdOfWebsiteByStoreId($subscriber->getStoreId());
            $fieldId = $this->customerResourceModel->getKeyId(self::OPT_IN, $sId);

            $payload = [
                'keyId' => $this->customerResourceModel->getKeyId(self::CUSTOMER_EMAIL, $sId),
                'keyValues' => [$subscriber->getSubscriberEmail()],
                'fieldId' => $fieldId,
            ];

            $websiteId = $this->storeManager->getStore($subscriber->getStoreId())->getWebsiteId();

            $this->api->setWebsiteId($websiteId);
            $response = $this->api->sendRequest('POST', 'contact/last_change', $payload);

            if (isset($response['body']['data']['result'][$subscriber->getSubscriberEmail()]['time'])) {
                $subscriberResponse = $response['body']['data']['result'][$subscriber->getSubscriberEmail()];
                $emarsysTime = $subscriberResponse['time'];
                $emarsysOptinChangeTime = $this->convertToUtc($emarsysTime);
                $magentoOptinChangeTime = $subscriber->getChangeStatusAt();

                if (isset($subscriberResponse['current_value'])) {
                    $emarsysOptinValue = $subscriberResponse['current_value'];
                }
                $magentoOptinValue = $subscriber->getSubscriberStatus();

                if ($emarsysOptinChangeTime >= $magentoOptinChangeTime && $emarsysOptinValue != $magentoOptinValue) {
                    if ($emarsysOptinValue == 1) {
                        $statusToBeChanged = Subscriber::STATUS_SUBSCRIBED;
                    } elseif ($emarsysOptinValue == 2) {
                        $statusToBeChanged = Subscriber::STATUS_UNSUBSCRIBED;
                    } else {
                        $statusToBeChanged = Subscriber::STATUS_NOT_ACTIVE;
                    }
                    if (!in_array($magentoOptinValue, [
                            Subscriber::STATUS_NOT_ACTIVE,
                            Subscriber::STATUS_UNCONFIRMED,
                        ]) && !in_array($statusToBeChanged, [
                            Subscriber::STATUS_NOT_ACTIVE,
                            Subscriber::STATUS_UNCONFIRMED,
                        ])
                    ) {
                        $subscriber->setSubscriberStatus($statusToBeChanged)
                            ->setEmarsysNoExport(true)
                            ->save();
                    }
                }
                return false;
            } elseif (isset($response['body']['data']['errors'][0]['errorCode'])
                && $response['body']['data']['errors'][0]['errorCode'] == 2008
            ) {
                $this->addNoticeLog(
                    $logMessage,
                    Zend_Json::encode($response),
                    $subscriber->getStoreId(),
                    'realtimeTimeBasedOptinSync'
                );
            } elseif (isset($response['status']) && $response['status'] == 200) {
                return true;
            } else {
                $this->addErrorLog(
                    $logMessage,
                    Zend_Json::encode($response),
                    $subscriber->getStoreId(),
                    'realtimeTimeBasedOptinSync'
                );
            }
        } catch (\Exception $e) {
            $this->addErrorLog(
                $logMessage,
                htmlentities($e->getMessage()),
                $subscriber->getStoreId(),
                'realtimeTimeBasedOptinSync'
            );
        }

        return true;
    }

    /**
     * @param $emarsysTime
     * @return string
     */
    public function convertToUtc($emarsysTime)
    {
        $emarsysDate = \DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $emarsysTime,
            new \DateTimeZone('Europe/Vienna')
        );
        $acst_date = clone $emarsysDate; // we don't want PHP's default pass object by reference here
        $acst_date->setTimeZone(new \DateTimeZone('UTC'));

        return $EmarsysOptinChangeTime = $acst_date->format('Y-m-d H:i:s');  // UTC:  2011-04-27 2:exit;
    }

    /**
     * Time based optin sync for backgourd type
     *
     * @param $subscriberIdsArray
     */
    public function backgroudTimeBasedOptinSync($subscriberIdsArray)
    {
        try {
            $sId = $this->getFirstStoreIdOfWebsite($this->websiteId);

            $logsArray['job_code'] = 'Backgroud Time Based Optin Sync';
            $logsArray['status'] = 'started';
            $logsArray['messages'] = 'Backgroud Time Based Optin Sync';
            $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = 'Automatic';
            $logsArray['auto_log'] = 'Complete';
            $logsArray['store_id'] = $sId;
            $logId = $this->logsHelper->manualLogs($logsArray);

            $subscribersCollection = $this->newsLetterCollectionFactory->create()
                ->addFieldToFilter('subscriber_id', ['in' => $subscriberIdsArray]);
            $magLastModifiedStatus = [];
            foreach ($subscribersCollection as $_subscriber) {
                $magLastModifiedStatus[$_subscriber->getSubscriberEmail()] = [
                    'change_status_at' => $_subscriber->getChangeStatusAt(),
                    'subscriber_status' => $_subscriber->getSubscriberStatus(),
                    'subscriber_id' => $_subscriber->getId(),
                ];
            }

            $fieldId = $this->customerResourceModel->getKeyId(self::OPT_IN, $sId);
            $emailKey = $this->customerResourceModel->getKeyId(self::CUSTOMER_EMAIL, $sId);

            $payload = [
                'keyId' => $emailKey,
                'keyValues' => array_keys($magLastModifiedStatus),
                'fieldId' => $fieldId,
            ];

            $this->api->setWebsiteId($this->websiteId);
            $response = $this->api->sendRequest('POST', 'contact/last_change', $payload);
            $statuses = [];
            if (isset($response['body']['data']['result'])) {
                $emarsysSubscribers = $response['body']['data']['result'];
                foreach ($emarsysSubscribers as $emarsysSubscriberKey => $emarsysSubscriberValue) {
                    $magentoLastUpdatedTime = $magLastModifiedStatus[$emarsysSubscriberKey]['change_status_at'];
                    $magentoSubscriptionStatus = $magLastModifiedStatus[$emarsysSubscriberKey]['subscriber_status'];
                    $subscriberId = $magLastModifiedStatus[$emarsysSubscriberKey]['subscriber_id'];

                    $currentEmarsysSubcsriptionStatus = $emarsysSubscriberValue['current_value'];
                    $emarsysLastUpdateTime = $this->convertToUtc($emarsysSubscriberValue['time']);

                    if ($currentEmarsysSubcsriptionStatus == 1) {
                        $statusToBeChanged = Subscriber::STATUS_SUBSCRIBED;
                    } elseif ($currentEmarsysSubcsriptionStatus == 2) {
                        $statusToBeChanged = Subscriber::STATUS_UNSUBSCRIBED;
                    } else {
                        $statusToBeChanged = Subscriber::STATUS_NOT_ACTIVE;
                    }

                    if ($statusToBeChanged != $magentoSubscriptionStatus
                        && $emarsysLastUpdateTime >= $magentoLastUpdatedTime
                    ) {
                        $statuses[$statusToBeChanged][] = $subscriberId;
                    }
                }

                if (!empty($statuses)) {
                    foreach ($statuses as $status => $subscriberIds) {
                        $this->customerResourceModel->updateStatusOfSubscribers($status, $subscriberIds);
                        $logsArray['id'] = $logId;
                        $logsArray['emarsys_info'] = 'Backgroud Time Based Optin Sync Success';
                        $logsArray['description'] = 'Status: ' . $status
                            . ' set to: ' . Zend_Json::encode($subscriberIds);
                        $logsArray['action'] = 'Backgroud Time Based Optin Sync';
                        $logsArray['message_type'] = 'Success';
                        $logsArray['log_action'] = 'True';
                        $logsArray['website_id'] = $this->websiteId;
                        $this->logsHelper->logs($logsArray);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->addErrorLog(
                self::LOG_MESSAGE_SUBSCRIBER,
                htmlentities($e->getMessage()),
                0,
                'backgroudTimeBasedOptinSync'
            );
        }
    }

    /**
     * @param int $websiteId
     * @param bool $isTimeBased
     * @throws LocalizedException
     */
    public function importSubscriptionUpdates($websiteId, $isTimeBased = false)
    {
        try {
            $logsArray['job_code'] = 'Sync contact Export';
            $logsArray['status'] = 'started';
            $logsArray['messages'] = 'Running requestSubscriptionUpdates';
            $logsArray['description'] = 'Started Sync Contacts Subscription Data';
            $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = 'Automatic';
            $logsArray['auto_log'] = 'Complete';
            $logsArray['website_id'] = $websiteId;
            $logsArray['store_id'] = 0;
            $logId = $this->logsHelper->manualLogs($logsArray);
            $logsArray['id'] = $logId;

            if ($this->isEmarsysEnabled($websiteId)) {
                $this->websiteId = $websiteId;
                $offset = 0;
                $limit = 500;
                do {
                    $exportId = $this->scopeConfigInterface->getValue(
                        'emarsys_suite2/storage/export_id',
                        ScopeInterface::SCOPE_WEBSITE,
                        $this->websiteId
                    );

                    $apiCall = sprintf('export/%s/data/offset=%s&limit=%s', $exportId, $offset, $limit);
                    $message = 'Request ' . $apiCall . ' ';
                    $this->api->setWebsiteId($websiteId);
                    $response = $this->api->sendRequest('GET', $apiCall);
                    $message .= "\nResponse: " . (Zend_Json::encode($response));
                    $logsArray['messages'] = $message;
                    $this->logsHelper->logs($logsArray);
                    $offset += $limit;
                    if (!isset($response['body']) || empty($response['body'])) {
                        break;
                    }
                } while ($this->_processSubscriptionUpdates($response['body'], $isTimeBased));
            }
        } catch (\Exception $e) {
            $this->addErrorLog(
                self::LOG_MESSAGE_SUBSCRIBER,
                $e->getMessage(),
                0,
                'importSubscriptionUpdates(helperData)'
            );
        }
    }

    /**
     * @param $contents
     * @param bool $isTimeBased
     * @return bool
     * @throws \Exception
     */
    protected function _processSubscriptionUpdates($contents, $isTimeBased = false)
    {
        if ($contents) {
            $lines = explode(PHP_EOL, $contents);
            $changedOptinArray = [];
            foreach ($lines as $line) {
                $changedOptinArray[] = str_getcsv($line);
            }

            if ((!isset($changedOptinArray) || count($changedOptinArray) <= 1)
                || (count($changedOptinArray) == 2
                    && (!isset($changedOptinArray[1][0]) || empty($changedOptinArray[1][0])))
            ) {
                $logsArray['messages'] = 'No opt-in updates';
                $this->logsHelper->manualLogs($logsArray);
                return false;
            }

            $subscriberIds = [];

            foreach ($changedOptinArray as $optIn) {
                if (isset($optIn['1']) && is_numeric($optIn['1'])) {
                    $subscriberIds[] = $optIn['1'];
                }
            }
            if (count($subscriberIds) > 0) {
                $this->backgroudTimeBasedOptinSync($subscriberIds);
            }

            return true;
        }

        return false;
    }

    /**
     * Get Emarsys Latest Version Information
     *
     * @return mixed
     * @throws \Exception
     */
    public function getEmarsysLatestVersionInfo()
    {
        $apiUrl = $this->scopeConfigInterface->getValue(
            'emartech/ftp_settings/apiurl',
            'default',
            0
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13'
        );
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $data = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception(curl_errno($ch));
        } else {
            curl_close($ch);
            return $data;
        }
    }

    /**
     * @param null|string|bool|int|StoreInterface $store
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function checkFtpConnectionByStore($store)
    {
        $result = false;

        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore($store);
        $hostname = $store->getConfig(self::XPATH_EMARSYS_FTP_HOSTNAME);
        $port = $store->getConfig(self::XPATH_EMARSYS_FTP_PORT);
        $username = $store->getConfig(self::XPATH_EMARSYS_FTP_USERNAME);
        $password = $store->getConfig(self::XPATH_EMARSYS_FTP_PASSWORD);
        $ftpSsl = $store->getConfig(self::XPATH_EMARSYS_FTP_USEFTP_OVER_SSL);
        $passiveMode = $store->getConfig(self::XPATH_EMARSYS_FTP_USE_PASSIVE_MODE);

        if (!$username || !$password || !$hostname || !$port) {
            return $result;
        }

        $result = $this->ftp->open([
            'host' => $hostname,
            'port' => $port,
            'user' => $username,
            'password' => $password,
            'ssl' => $ftpSsl ? true : false,
            'passive' => $passiveMode ? true : false,
        ]);

        return $result;
    }

    /**
     * @param null|string|bool|int|StoreInterface $store
     * @param $filePath
     * @param $filename
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function moveFileToFtp($store, $filePath, $filename)
    {
        $result = false;
        if ($this->checkFtpConnectionByStore($store)) {
            $result = $this->ftp->write($filename, $filePath);
            $this->ftp->close();
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getSalesOrderCsvDefaultHeader()
    {
        return ['order', 'timestamp', 'email', 'item', 'price', 'quantity'];
    }

    /**
     * @param $folderName
     * @return bool
     * @throws \Exception
     */
    public function checkAndCreateFolder($folderName)
    {
        try {
            if ($this->filesystemIoFile->checkAndCreateFolder($folderName)) {
                if ($this->filesystemIoFile->chmod($folderName, 0775, true)) {
                    return true;
                }
            }
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * @param $folderName
     * @return string
     * @throws FileSystemException
     */
    public function getEmarsysMediaDirectoryPath($folderName)
    {
        return $this->directoryList->getPath(DirectoryList::MEDIA) . '/emarsys/' . $folderName;
    }

    /**
     * @param $folderName
     * @param $csvFilePath
     * @return string
     * @throws NoSuchEntityException
     */
    public function getEmarsysMediaUrlPath($folderName, $csvFilePath)
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA)
            . 'emarsys/' . $folderName . '/' . basename($csvFilePath);
    }

    /**
     * @param $entity
     * @param $storeCode
     * @return string
     */
    public function getCustomerCsvFileName($entity, $storeCode)
    {
        if ($entity == \Magento\Customer\Model\Customer::ENTITY) {
            $entityCode = 'customers_';
        } else {
            $entityCode = 'subscribers_';
        }

        return $entityCode . $this->date->date('YmdHis', time()) . "_" . $storeCode . ".csv";
    }

    /**
     * @param $outputFile
     * @return string
     */
    public function getContactCsvGenerationPath($outputFile)
    {
        return BP . '/var/' . $outputFile;
    }

    /**
     * @param $messages
     * @param $description
     * @param $storeId
     * @param $info
     */
    public function addErrorLog($messages = '', $description = '', $storeId = 0, $info = '')
    {
        return $this->emarsysLogs->addErrorLog($messages, $description, $storeId, $info);
    }

    /**
     * @param $messages
     * @param $description
     * @param $storeId
     * @param $info
     */
    public function addNoticeLog($messages = '', $description = '', $storeId = 0, $info = '')
    {
        return $this->emarsysLogs->addNoticeLog($messages, $description, $storeId, $info);
    }

    /**
     * @param $messages
     * @param $description
     * @param $storeId
     * @param $info
     */
    public function addSuccessLog($messages = '', $description = '', $storeId = 0, $info = '')
    {
        return $this->emarsysLogs->addSuccessLog($messages, $description, $storeId, $info);
    }

    /**
     * @param string $fileDirectory
     * @return bool
     */
    public function removeFilesInFolder($fileDirectory)
    {
        if ($handle = opendir($fileDirectory)) {
            while (false !== ($file = readdir($handle))) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                $filePath = $fileDirectory . '/' . $file;
                $fileLastModified = filemtime($filePath);
                if ((time() - $fileLastModified) > 1 * 24 * 3600) {
                    unlink($filePath);
                }
            }
            closedir($handle);
        }
        return true;
    }

    /**
     * @param $websiteId
     * @return $this
     */
    public function setWebsiteId($websiteId)
    {
        $this->websiteId = $websiteId;
        return $this;
    }

    /**
     * @param $storeId
     * @return bool|mixed
     */
    public function getEmarsysCustomerSchema($storeId)
    {
        try {
            $store = $this->storeManager->getStore($storeId);
            $this->api->setWebsiteId($store->getWebsiteId());
            $response = $this->api->sendRequest('GET', 'field/translate/en');
            return $response['body'];
        } catch (\Exception $e) {
            $this->addErrorLog(
                'getEmarsysCustomerSchema',
                htmlentities($e->getMessage()),
                $storeId,
                'getEmarsysCustomerSchema'
            );
        }
        return false;
    }
}
