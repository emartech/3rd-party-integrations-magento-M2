<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Installation;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Model\ResourceModel\CustomerFactory as CustomerResourceModel;
use Magento\Backend\Block\Template\Context;

class Checklist extends \Magento\Backend\Block\Template
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $requestInterface;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var CustomerResourceModel
     */
    protected $customerResourceModel;

    /**
     * Checklist constructor.
     *
     * @param Context $context
     * @param EmarsysHelper $emarsysHelper
     * @param CustomerResourceModel $customerResourceModel
     * @param array $data
     */
    public function __construct(
        Context $context,
        EmarsysHelper $emarsysHelper,
        CustomerResourceModel $customerResourceModel,
        array $data = []
    ) {
        $this->storeManagerInterface = $context->getStoreManager();
        $this->requestInterface = $context->getRequest();
        $this->emarsysHelper = $emarsysHelper;
        $this->customerResourceModel = $customerResourceModel;
        parent::__construct($context, $data);
    }

    public function getEmarsysCustomerFieldNames()
    {
        $storeId = $this->getStoreId();
        $emarsysCustomerFieldNames = [];
        if ($storeId) {
            $emarsysCustomerFields = $this->emarsysHelper->getEmarsysCustomerSchema($storeId);
            if (is_array($emarsysCustomerFields) && isset($emarsysCustomerFields['data'])) {
                $emarsysCustomerDataFields = $emarsysCustomerFields['data'];
                foreach ($emarsysCustomerDataFields as $сustomerField) {
                    $emarsysCustomerFieldNames[$сustomerField['id']] = $сustomerField['name'];
                }
            }
        }
        return $emarsysCustomerFieldNames;
    }

    public function getStore()
    {
        $storeId = $this->getStoreId();
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);

        if ($storeId) {
            $store = $this->storeManagerInterface->getStore($storeId);
            return $store;
        }
        return false;
    }

    public function getStoreId()
    {
        return $this->requestInterface->getParam('store');
    }

    public function getRequirementsInfo($storeId)
    {
        return $this->emarsysHelper->getRequirementsInfo($storeId);
    }

    public function getCustomerId()
    {
        return $this->customerResourceModel->create()->getKeyId(
            EmarsysHelper::CUSTOMER_ID,
            $this->getStore()->getId()
        );
    }

    public function getSubscriberId()
    {
        return $this->customerResourceModel->create()->getKeyId(
            EmarsysHelper::SUBSCRIBER_ID,
            $this->getStore()->getId()
        );
    }
}
