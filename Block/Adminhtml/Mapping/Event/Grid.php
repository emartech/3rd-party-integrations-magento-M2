<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Mapping\Event;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Helper\Logs as EmarsysHelperLogs;
use Emarsys\Emarsys\Model\EmarsyseventmappingFactory as EmarsysEventsFactory;
use Emarsys\Emarsys\Model\EmarsysmagentoeventsFactory as EmarsysMagentoEvents;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Backend\Helper\Data;
use Magento\Backend\Model\Session;
use Magento\Framework\App\ResourceConnection;

class Grid extends Extended
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var EmarsysEventsFactory
     */
    protected $emarsysEventsFactory;

    /**
     * @var EmarsysMagentoEvents
     */
    protected $emarsysMagentoEvents;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var EmarsysHelperLogs
     */
    protected $logsHelper;

    /**
     * Grid constructor.
     *
     * @param EmarsysMagentoEvents $emarsysMagentoEvents
     * @param EmarsysEventsFactory $emarsysEventsFactory
     * @param EmarsysHelper $emarsysHelper
     * @param ResourceConnection $resourceConnection
     * @param EmarsysHelperLogs $logsHelper
     * @param Context $context
     * @param Data $backendHelper
     * @param array $data
     */
    public function __construct(
        EmarsysMagentoEvents $emarsysMagentoEvents,
        EmarsyseventsFactory $emarsysEventsFactory,
        EmarsysHelper $emarsysHelper,
        ResourceConnection $resourceConnection,
        EmarsysHelperLogs $logsHelper,
        Context $context,
        Data $backendHelper,
        $data = []
    ) {
        $this->session = $context->getBackendSession();
        $this->emarsysMagentoEvents = $emarsysMagentoEvents;
        $this->emarsysEventsFactory = $emarsysEventsFactory;
        $this->emarsysHelper = $emarsysHelper;
        $this->resourceConnection = $resourceConnection;
        $this->logsHelper = $logsHelper;
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * @return Extended
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareCollection()
    {
        $eventMappingCollection = $this->emarsysMagentoEvents->create()->getCollection();
        $storeId = (int)$this->getRequest()->getParam('store');
        if (!$storeId) {
            $storeId = $this->emarsysHelper->getFirstStoreId();
        }

        $eventMappingCollection->getSelect()->joinLeft(
            ['emarsys_event_mapping' => $this->resourceConnection->getTableName('emarsys_event_mapping')],
            'main_table.id = emarsys_event_mapping.magento_event_id AND emarsys_event_mapping.store_id = ' . $storeId,
            ['emarsys_event_id']
        );
        $eventMappingCollection->setOrder('main_table.id', 'ASC');

        $this->setCollection($eventMappingCollection);
        return parent::_prepareCollection();
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('mappingEventGrid');
        $this->setSaveParametersInSession(true);
        $storeId = $this->getRequest()->getParam('store');
        if (!$storeId) {
            $storeId = $this->emarsysHelper->getFirstStoreId();
        }

        $this->session->setData('store', $storeId);
        $this->session->setData('storeId', $storeId);
        $this->session->setMappingGridData([]);
        $collection = $this->emarsysEventsFactory->create()->getCollection()
            ->addFieldToFilter('store_id', $storeId);

        $mapGridArray = [];
        foreach ($collection as $col) {
            $mapGridArray[$col->getData('magento_event_id')]['magento_event_id'] = $col->getData('magento_event_id');
            $mapGridArray[$col->getData('magento_event_id')]['emarsys_event_id'] = $col->getData('emarsys_event_id');
        }

        $this->setDefaultSort('id');
        $this->setDefaultDir('ASC');
        $this->session->setMappingGridData($mapGridArray);
        $this->session->setStoreId($storeId);
    }

    /**
     * @return Extended
     * @throws \Exception
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'emarsys_event',
            ['header' => __('Magento Event'), 'index' => 'magento_event']
        );

        $recommended = $this->getRequest()->getParam('recommended');

        if (isset($recommended) && $recommended != "") {
            $this->addColumn(
                'emarsys_event_id',
                [
                    'header' => __('Emarsys Event'),
                    'renderer' => \Emarsys\Emarsys\Block\Adminhtml\Mapping\Event\Renderer\Emarsyseventmapping::class,
                    'filter' => false,
                ]
            );
        } else {
            $this->addColumn(
                'emarsys_event_id',
                [
                    'header' => __('Emarsys Event'),
                    'renderer' => \Emarsys\Emarsys\Block\Adminhtml\Mapping\Event\Renderer\EmarsysEvent::class,
                    'filter' => false,
                ]
            );
        }

        return parent::_prepareColumns();
    }

    /**
     * @return string
     */
    public function getMainButtonsHtml()
    {
        return parent::getMainButtonsHtml();
    }

    /**
     * @param  $row
     * @return string
     */
    public function getRowUrl($row)
    {
        return '';
    }
}
