<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Mapping\Product;

use Emarsys\Emarsys\Helper\Data;
use Emarsys\Emarsys\Helper\Logs as EmarsysHelperLogs;
use Emarsys\Emarsys\Model\Logs;
use Emarsys\Emarsys\Model\ProductFactory;
use Emarsys\Emarsys\Model\ResourceModel\Product;
use Emarsys\Emarsys\Model\ResourceModel\Product\CollectionFactory;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Zend_Json;

class SaveRecommended extends Action
{
    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var CollectionFactory
     */
    protected $productAttributeCollection;

    /**
     * @var Data
     */
    protected $emarsysHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Logs
     */
    protected $emarsysLogs;

    /**
     * @var EmarsysHelperLogs
     */
    protected $logsHelper;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var Product
     */
    protected $resourceModelProduct;

    /**
     * SaveRecommended constructor.
     *
     * @param Context $context
     * @param ProductFactory $productFactory
     * @param CollectionFactory $productAttributeCollection
     * @param Data $emarsysHelper
     * @param StoreManagerInterface $storeManager
     * @param Logs $emarsysLogs
     * @param EmarsysHelperLogs $logsHelper
     * @param DateTime $date
     * @param Product $resourceModelProduct
     */
    public function __construct(
        Context $context,
        ProductFactory $productFactory,
        CollectionFactory $productAttributeCollection,
        Data $emarsysHelper,
        StoreManagerInterface $storeManager,
        Logs $emarsysLogs,
        EmarsysHelperLogs $logsHelper,
        DateTime $date,
        Product $resourceModelProduct
    ) {
        parent::__construct($context);
        $this->productFactory = $productFactory;
        $this->productAttributeCollection = $productAttributeCollection;
        $this->emarsysHelper = $emarsysHelper;
        $this->storeManager = $storeManager;
        $this->emarsysLogs = $emarsysLogs;
        $this->logsHelper = $logsHelper;
        $this->date = $date;
        $this->resourceModelProduct = $resourceModelProduct;
    }

    /**
     * @return Redirect
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $storeId = $this->getRequest()->getParam('store', false);
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();

        try {
            $recommendedArray = [];
            $logsArray['job_code'] = 'Product Mapping';
            $logsArray['status'] = 'started';
            $logsArray['messages'] = 'Running Update Schema';
            $logsArray['description'] = 'Started Recommended Mapping';
            $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = 'Automatic';
            $logsArray['auto_log'] = 'Complete';
            $logsArray['website_id'] = $websiteId;
            $logsArray['store_id'] = $storeId;
            $logId = $this->logsHelper->manualLogs($logsArray);
            /**
             *Here We need set the recommended attribute values
             */
            $data = $this->resourceModelProduct->getProductAttributeLabelId($storeId);
            $recommendedData = [
                'sku' => ['emarsys_attr_code' => $data[0]],
                'name' => ['emarsys_attr_code' => $data[1]],
                'url_key' => ['emarsys_attr_code' => $data[2]],
                'image' => ['emarsys_attr_code' => $data[3]],
                'category_ids' => ['emarsys_attr_code' => $data[4]],
                'price' => ['emarsys_attr_code' => $data[5]],
            ];
            // Remove existing data
            $this->resourceModelProduct->deleteRecommendedMappingExistingAttr($recommendedData, $storeId);
            foreach ($recommendedData as $key => $value) {
                $mappedAttributeCode = $this->productAttributeCollection->create()
                    ->addFieldToFilter('magento_attr_code', ['eq' => $key])
                    ->addFieldToFilter('store_id', ['eq' => $storeId])
                    ->getFirstItem()
                    ->getEmarsysAttrCode();
                if ($key == '' || $mappedAttributeCode) {
                    continue;
                }
                $recommendedArray[] = $value['magento_attr_code'] = $key;
                $model = $this->productFactory->create();
                $model = $model->setData($value);
                $model->setStoreId($storeId);
                $model->save();
            }
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Saved Recommended Mapping';
            $logsArray['action'] = 'Saved Recommended Mapping';
            $logsArray['message_type'] = 'Success';
            $logsArray['description'] = 'Saved Recommended Mapping as ' . Zend_Json::encode($recommendedArray);
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['log_action'] = 'True';
            $logsArray['status'] = 'success';
            $logsArray['messages'] = 'Product Recommended Mapping Saved Successfully';
            $this->logsHelper->manualLogs($logsArray);
            $this->messageManager->addSuccessMessage(__('Recommended Product attributes mapped successfully'));
        } catch (Exception $e) {
            $this->emarsysLogs->addErrorLog(
                'Product Recommended Mapping',
                $e->getMessage(),
                $storeId,
                'Save Recommended(Product)'
            );
            $this->messageManager->addErrorMessage(__('Error occurred while mapping Product attribute'));
        }
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setRefererOrBaseUrl();
    }
}
