<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Mapping\Field;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Helper\Logs;
use Emarsys\Emarsys\Model\FieldFactory;
use Emarsys\Emarsys\Model\ResourceModel\Field;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

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
     * @var FieldFactory
     */
    protected $fieldFactory;

    /**
     * @var Field
     */
    protected $resourceModelField;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

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
     * Save constructor.
     *
     * @param Context $context
     * @param FieldFactory $fieldFactory
     * @param Field $resourceModelField
     * @param PageFactory $resultPageFactory
     * @param Logs $logsHelper
     * @param EmarsysHelper $emarsysHelper
     * @param DateTime $date
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        FieldFactory $fieldFactory,
        Field $resourceModelField,
        PageFactory $resultPageFactory,
        Logs $logsHelper,
        EmarsysHelper $emarsysHelper,
        DateTime $date,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->session = $context->getSession();
        $this->date = $date;
        $this->emarsysHelper = $emarsysHelper;
        $this->resultPageFactory = $resultPageFactory;
        $this->resourceModelField = $resourceModelField;
        $this->fieldFactory = $fieldFactory;
        $this->logsHelper = $logsHelper;
        $this->storeManager = $storeManager;
    }

    /**
     * @return Redirect
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $session = $this->session->getData();
        $storeId = false;
        if (isset($session['store'])) {
            $storeId = $session['store'];
        }
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
        $resultRedirect = $this->resultRedirectFactory->create();
        try {
            $logsArray['job_code'] = 'Customer Filed Mapping';
            $logsArray['status'] = 'started';
            $logsArray['messages'] = 'Running Recommended Mapping';
            $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = 'Automatic';
            $logsArray['auto_log'] = 'Complete';
            $logsArray['website_id'] = $websiteId;
            $logsArray['store_id'] = $storeId;
            $model = $this->fieldFactory->create();
            $session = $this->session->getData();

            $gridSessionData = [];
            if (isset($session['gridData'])) {
                $gridSessionData = $session['gridData'];
            }
            if (isset($gridSessionData) && $gridSessionData != '') {
                foreach ($gridSessionData as $magentoOptionId => $emarsysFieldOption) {
                    $modelColl = $model->getCollection()
                        ->addFieldToFilter('magento_option_id', $magentoOptionId)
                        ->addFieldToFilter('store_id', $storeId)
                        ->getFirstItem();

                    if (trim($emarsysFieldOption) == '') {
                        $modelColl->delete();
                        continue;
                    }

                    list($emarsysFieldId, $emarsysOptionId) = explode('-', $emarsysFieldOption);

                    $modelColl->setEmarsysOptionId(trim($emarsysOptionId));
                    $modelColl->setEmarsysFieldId(trim($emarsysFieldId));
                    $modelColl->setMagentoOptionId(trim($magentoOptionId));
                    $modelColl->setStoreId($storeId);
                    $modelColl->save();
                }
            }
            $logId = $this->logsHelper->manualLogs($logsArray);
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Save Customer Filed Mapping';
            $logsArray['description'] = 'Save Customer Filed Mapping Successful';
            $logsArray['action'] = 'Save Customer Filed Mapping Successful';
            $logsArray['message_type'] = 'Success';
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['log_action'] = 'True';
            $logsArray['status'] = 'success';
            $logsArray['messages'] = 'Save Customer Filed Mapping Successful';
            $this->logsHelper->manualLogs($logsArray);
            $this->messageManager->addSuccessMessage(__('Customer-Field attributes mapped successfully'));
        } catch (Exception $e) {
            $this->emarsysHelper->addErrorLog(
                'Customer Filed Mapping',
                $e->getMessage(),
                $storeId,
                'Save (Customer Filed)'
            );
            $this->messageManager->addErrorMessage(__('Error occurred while mapping Customer-Field'));
        }

        return $resultRedirect->setRefererOrBaseUrl();
    }
}
