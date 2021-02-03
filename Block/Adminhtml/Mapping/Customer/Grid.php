<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Mapping\Customer;

use Emarsys\Emarsys\Block\Adminhtml\Mapping\Customer\Renderer\EmarsysCustomer;
use Emarsys\Emarsys\Block\Adminhtml\Mapping\Customer\Renderer\MagentoAttribute;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Model\CustomerMagentoAttsFactory;
use Emarsys\Emarsys\Model\ResourceModel\Customer;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Backend\Helper\Data as BackendHelper;
use Magento\Backend\Model\Session;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Exception;

class Grid extends Extended
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Customer
     */
    protected $resourceModelCustomer;

    /**
     * @var Attribute
     */
    protected $attribute;

    /**
     * @var CustomerMagentoAttsFactory
     */
    protected $customerMagentoAttsFactory;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * Grid constructor.
     *
     * @param Context $context
     * @param BackendHelper $backendHelper
     * @param Attribute $attribute
     * @param Customer $resourceModelCustomer
     * @param CustomerMagentoAttsFactory $customerMagentoAttsFactory
     * @param EmarsysHelper $emarsysHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        BackendHelper $backendHelper,
        Attribute $attribute,
        Customer $resourceModelCustomer,
        CustomerMagentoAttsFactory $customerMagentoAttsFactory,
        EmarsysHelper $emarsysHelper,
        $data = []
    ) {
        $this->session = $context->getBackendSession();
        $this->attribute = $attribute;
        $this->resourceModelCustomer = $resourceModelCustomer;
        $this->customerMagentoAttsFactory = $customerMagentoAttsFactory;
        $this->emarsysHelper = $emarsysHelper;
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * @return $this
     * @throws LocalizedException
     */
    protected function _prepareCollection()
    {
        $this->session->setData('gridData', '');
        $storeId = $this->getRequest()->getParam('store');
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);

        $collection = $this->customerMagentoAttsFactory->create()
            ->getCollection()
            ->addFieldToFilter('store_id', ['eq' => $storeId]);
        $collection->setOrder('main_table.frontend_label', 'ASC');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * @return $this
     * @throws Exception
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function _prepareColumns()
    {

        $this->addColumn(
            'frontend_label',
            [
                'header' => __('Magento Customer Attribute'),
                'type' => 'varchar',
                'index' => 'frontend_label',
                'header_css_class' => 'col-id',
                'column_css_class' => 'col-id',
                'renderer' => MagentoAttribute::class,
            ]
        );
        $this->addColumn(
            'emarsys_contact_header',
            [
                'header' => __('Emarsys Customer Attribute'),
                'renderer' => EmarsysCustomer::class,
                'filter' => false,
            ]
        );

        return parent::_prepareColumns();
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct()
    {
        parent::_construct();
        $this->session->setData('gridData', '');
        $storeId = $this->getRequest()->getParam('store');
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);

        $this->session->setData('store', $storeId);
        $mappingExists = $this->resourceModelCustomer->customerMappingExists($storeId);
        if ($mappingExists == false) {
            $customerAttData = $this->attribute->getCollection()
                ->addFieldToSelect('frontend_label')
                ->addFieldToSelect('attribute_code')
                ->addFieldToSelect('entity_type_id')
                ->addFieldToFilter('entity_type_id', ['in' => '1, 2'])
                ->getData();
            $this->resourceModelCustomer->insertCustomerMageAtts($customerAttData, $storeId);
        }
    }

    /**
     * @param DataObject $row
     * @return string
     */
    public function getRowUrl($row)
    {
        return '';
    }
}
