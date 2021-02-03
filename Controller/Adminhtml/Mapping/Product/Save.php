<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Mapping\Product;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\View\Result\PageFactory;
use Emarsys\Emarsys\Model\ProductFactory;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Magento\Store\Model\StoreManagerInterface;
use Emarsys\Emarsys\Helper\Logs;
use Emarsys\Emarsys\Model\Logs as EmarsysModelLogs;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Emarsys\Emarsys\Model\ResourceModel\Product;
use Zend_Json;

class Save extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var EmarsysModelLogs
     */
    protected $emarsysLogs;

    /**
     * @var Product
     */
    protected $resourceModelProduct;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var Logs
     */
    protected $logsHelper;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param ProductFactory $productFactory
     * @param EmarsysHelper $emarsysHelper
     * @param StoreManagerInterface $storeManager
     * @param Logs $logsHelper
     * @param EmarsysModelLogs $emarsysLogs
     * @param DateTime $date
     * @param Product $resourceModelProduct
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        ProductFactory $productFactory,
        EmarsysHelper $emarsysHelper,
        StoreManagerInterface $storeManager,
        Logs $logsHelper,
        EmarsysModelLogs $emarsysLogs,
        DateTime $date,
        Product $resourceModelProduct,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->session = $context->getSession();
        $this->resultPageFactory = $resultPageFactory;
        $this->productFactory = $productFactory;
        $this->emarsysLogs = $emarsysLogs;
        $this->resourceModelProduct = $resourceModelProduct;
        $this->emarsysHelper = $emarsysHelper;
        $this->logsHelper = $logsHelper;
        $this->date = $date;
        $this->storeManager = $storeManager;
    }

    /**
     * Save Action
     *
     * @return $this
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $gridSessionStoreId = 0;
        try {
            $savedValues = [];
            $model = $this->productFactory->create();
            $session = $this->session->getData();
            $gridSessionData = [];
            if (isset($session['gridData'])) {
                $gridSessionData = $session['gridData'];
            }
            if (isset($session['storeId'])) {
                $gridSessionStoreId = $session['storeId'];
            }
            $gridSessionStoreId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($gridSessionStoreId);

            $websiteId = $this->storeManager->getStore($gridSessionStoreId)->getWebsiteId();
            $logsArray['job_code'] = 'Product Mapping';
            $logsArray['status'] = 'started';
            $logsArray['messages'] = 'Running Update Schema';
            $logsArray['description'] = 'Saving Product Mapping';
            $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = 'Automatic';
            $logsArray['auto_log'] = 'Complete';
            $logsArray['website_id'] = $websiteId;
            $logsArray['store_id'] = $gridSessionStoreId;
            $logId = $this->logsHelper->manualLogs($logsArray);
            foreach ($gridSessionData as $key => $value) {
                if ($key == '') {
                    continue;
                }
                $value['magento_attr_code'] = $key;
                $modelColl = $model->getCollection()
                    ->addFieldToFilter('magento_attr_code', $key)
                    ->addFieldToFilter('store_id', $gridSessionStoreId);

                $modelCollData = $modelColl->getData();
                foreach ($modelCollData as $cols) {
                    $colsValue = $cols['emarsys_contact_field'];
                }

                if (isset($colsValue)) {
                    $model = $model->load($colsValue);
                }

                if (!empty($modelCollData)) {
                    foreach ($modelColl as $model) {
                        //Delete exsisting record
                        $this->resourceModelProduct->deleteExistingEmarsysAttr(
                            $value['emarsys_attr_code'],
                            $gridSessionStoreId
                        );
                        if (isset($value['emarsys_attr_code'])) {
                            $model->setEmarsysAttrCode($value['emarsys_attr_code']);
                        }
                        $model->setStoreId($gridSessionStoreId);
                        $savedValues[] = $value['emarsys_attr_code'];
                        $model->save();
                    }
                } else {
                    //Delete exsisting record
                    $this->resourceModelProduct->deleteExistingEmarsysAttr(
                        $value['emarsys_attr_code'],
                        $gridSessionStoreId
                    );
                    $model = $this->productFactory->create();
                    $model = $model->setData($value);
                    $model->setStoreId($gridSessionStoreId);
                    $savedValues[] = $value['emarsys_attr_code'];
                    $model->save();
                }
            }
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Save Product Mapping';
            $logsArray['action'] = 'Save Product Mapping Successful';
            $logsArray['message_type'] = 'Success';
            $logsArray['description'] = 'Product Mapping Saved as ' . Zend_Json::encode($savedValues);
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['log_action'] = 'True';
            $logsArray['status'] = 'success';
            $logsArray['messages'] = 'Save Product Mapping Saved Successfully';
            $this->logsHelper->manualLogs($logsArray);
            /**
             * Truncating the Mapping Table first
             */
            $this->resourceModelProduct->deleteUnmappedRows($gridSessionStoreId);
            $this->messageManager->addSuccessMessage(__('Product attributes Mapped successfully'));
        } catch (Exception $e) {
            $this->emarsysLogs->addErrorLog(
                'Saving Product Mapping',
                $e->getMessage(),
                $gridSessionStoreId,
                'Save(Product)'
            );
            $this->messageManager->addErrorMessage(__('Error occurred while mapping Product attribute'));
        }
        return $resultRedirect->setRefererOrBaseUrl();
    }
}
