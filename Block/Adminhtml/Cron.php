<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Schedular
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Backend\Helper\Data;
use Magento\Backend\Model\Session;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection as AttributeCollection;
use Magento\Framework\Data\Collection;
use Magento\Framework\DataObjectFactory;

class Cron extends Extended
{
    /**
     * @var AttributeCollection
     */
    protected $_collection;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Data
     */
    protected $backendHelper;

    /**
     * @var Collection
     */
    protected $dataCollection;

    /**
     * @var DataObjectFactory
     */
    protected $dataObjectFactory;

    /**
     * @var CronData
     */
    protected $cronData;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * Cron constructor.
     *
     * @param Context $context
     * @param Data $backendHelper
     * @param Collection $dataCollection
     * @param DataObjectFactory $dataObjectFactory
     * @param CronData $cronData
     * @param EmarsysHelper $emarsysHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $backendHelper,
        Collection $dataCollection,
        DataObjectFactory $dataObjectFactory,
        CronData $cronData,
        EmarsysHelper $emarsysHelper,
        $data = []
    ) {
        $this->session = $context->getBackendSession();
        $this->backendHelper = $backendHelper;
        $this->dataCollection = $dataCollection;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->cronData = $cronData;
        $this->emarsysHelper = $emarsysHelper;
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * Constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('LogsGrid');
        $this->setDefaultSort('frontend_label');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        $this->setVarNameFilter('frontend_label');
        $storeId = $this->getRequest()->getParam('store');
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);
        $this->session->setData('storeId', $storeId);
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function _prepareCollection()
    {
        $count = 1;
        $rows = $this->cronData->getCronData();
        $collection = $this->dataCollection;
        foreach ($rows as $row) {
            foreach ($row as $key => $datahere) {
                $datahere['rowid'] = $count;
                /* condition to filter only emarsys starts here,
                to get all the crons in this mageto remove the below if condition */
                if (isset($datahere['name'])) {
                    $emarsysexp = explode("_", $datahere['name']);
                }
                if ($emarsysexp[0] != 'emarsys') {
                    continue;
                }

                /* filter code ends here */
                // use this create method to prevent getting only the last data in the grid.
                $rowObj = $this->dataObjectFactory->create();
                $rowObj->setData($datahere)->toJson();
                $collection->addItem($rowObj);
                $collection->loadData();
                $count++;
            }
        }

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn(
            "name",
            [
                "header" => __("Code"),
                "align" => "left",
                'width' => '25',
                "index" => "name",
            ]
        );

        $this->addColumn(
            "method",
            [
                "header" => __("Method"),
                "align" => "center",
                "index" => "method",
                'width' => '150',

            ]
        );

        $this->addColumn(
            "schedule",
            [
                "header" => __("Schedule"),
                "align" => "left",
                "index" => "schedule",
                'width' => '150',

            ]
        );

        $this->addColumn(
            "rowid",
            [
                "header" => __("Action"),
                "align" => "left",
                "index" => "rowid",
                'renderer' => \Emarsys\Emarsys\Block\Adminhtml\Cron\Renderer\Messagetype::class,
                'width' => '150',
            ]
        );
        return parent::_prepareColumns();
    }
}
