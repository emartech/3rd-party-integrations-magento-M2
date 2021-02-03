<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Mapping\Order;

use Emarsys\Emarsys\Block\Adminhtml\Mapping\Order\Renderer\EmarsysOrderField;
use Emarsys\Emarsys\Helper\Data;
use Emarsys\Emarsys\Model\OrderFactory;
use Emarsys\Emarsys\Model\ResourceModel\Order;
use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Backend\Helper\Data as BackendHelper;
use Magento\Backend\Model\Session;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Zend_Db_Statement_Exception;

class Grid extends Extended
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var BackendHelper
     */
    protected $backendHelper;

    /**
     * @var Order
     */
    protected $resourceModelOrder;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var Data
     */
    protected $emarsysHelper;

    /**
     * Grid constructor.
     *
     * @param Context $context
     * @param BackendHelper $backendHelper
     * @param Order $resourceModelOrder
     * @param OrderFactory $orderFactory
     * @param Data $emarsysHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        BackendHelper $backendHelper,
        Order $resourceModelOrder,
        OrderFactory $orderFactory,
        Data $emarsysHelper,
        $data = []
    ) {
        $this->session = $context->getBackendSession();
        $this->backendHelper = $backendHelper;
        $this->resourceModelOrder = $resourceModelOrder;
        $this->orderFactory = $orderFactory;
        $this->emarsysHelper = $emarsysHelper;
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * @return Extended
     * @throws LocalizedException
     */
    protected function _prepareCollection()
    {
        $storeId = $this->getRequest()->getParam('store');
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);
        $customCollection = $this->orderFactory->create()->getCollection()
            ->addFilter('store_id', $storeId)
            ->setOrder('magento_column_name', 'ASC');
        $this->setCollection($customCollection);

        return parent::_prepareCollection();
    }

    /**
     * @return Grid
     * @throws Exception
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'magento_column_name',
            [
                'header' => __('Magento Column Name'),
                'index' => 'magento_column_name',
            ]
        );
        $this->addColumn(
            'entity_type_id',
            [
                'header' => __('Magento Entity Type Id'),
                'align' => 'left',
                'index' => 'entity_type_id',
                'column_css_class' => 'no-display',
                'header_css_class' => 'no-display',
            ]
        );
        $this->addColumn(
            'emarsys_order_field',
            [
                'header' => __('Emarsys Order Attribute'),
                'renderer' => EmarsysOrderField::class,
                'filter' => false,
            ]
        );

        return parent::_prepareColumns();
    }

    /**
     * @throws LocalizedException
     * @throws Zend_Db_Statement_Exception
     */
    protected function _construct()
    {
        parent::_construct();
        $this->session->setData('gridData', '');
        $storeId = $this->getRequest()->getParam('store');
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);
        $this->session->setData('store', $storeId);
        $mappingExists = $this->resourceModelOrder->orderMappingExists($storeId);
        if (empty($mappingExists)) {
            $header = $this->emarsysHelper->getSalesOrderCsvDefaultHeader();
            foreach ($header as $column) {
                $manData[$column] = $column;
            }

            $this->resourceModelOrder->insertIntoMappingTableStaticData($manData, $storeId);
            $data = $this->resourceModelOrder->getSalesOrderColumnNames();
            $this->resourceModelOrder->insertIntoMappingTable($data, $storeId);
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
