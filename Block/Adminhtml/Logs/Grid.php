<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Logs;

use Emarsys\Emarsys\Block\Adminhtml\Logs\Renderer\Messagetype;
use Emarsys\Emarsys\Model\LogsFactory;
use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Backend\Helper\Data;
use Magento\Backend\Model\Session;

class Grid extends Extended
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Data
     */
    protected $backendHelper;

    /**
     * @var LogsFactory
     */
    protected $logsFactory;

    /**
     * Grid constructor.
     *
     * @param Context $context
     * @param Data $backendHelper
     * @param LogsFactory $logsFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $backendHelper,
        LogsFactory $logsFactory,
        $data = []
    ) {
        $this->session = $context->getBackendSession();
        $this->backendHelper = $backendHelper;
        $this->logsFactory = $logsFactory;
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('logsGrid');
        $this->setDefaultSort('frontend_label');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setVarNameFilter('frontend_label');
        $storeId = $this->getRequest()->getParam('store');
        $this->session->setData('storeId', $storeId);
    }

    /**
     * @return $this
     */
    protected function _prepareCollection()
    {
        $scheduleId = $this->_request->getParam('schedule_id');
        $collection = $this->logsFactory->create()->getCollection()->addFieldToFilter('log_exec_id', $scheduleId);
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * @return $this
     * @throws Exception
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            "id",
            [
                "header" => __("Id"),
                "align" => "left",
                'width' => '10',
                "index" => "id",
            ]
        );

        $this->addColumn(
            "created_at",
            [
                "header" => __("Creation Date"),
                "align" => "left",
                'width' => '25',
                "index" => "created_at",
                'type' => 'datetime',
            ]
        );

        $this->addColumn(
            "emarsys_info",
            [
                "header" => __("Emarsys Info"),
                "align" => "left",
                'width' => '25',
                "index" => "emarsys_info",
            ]
        );

        $this->addColumn(
            "description",
            [
                "header" => __("Description"),
                "align" => "left",
                'width' => '25',
            ]
        );

        $this->addColumn(
            "description",
            [
                "header" => __("Description"),
                "align" => "left",
                'width' => '25',
                "index" => "description",
            ]
        );

        $this->addColumn(
            "message_type",
            [
                "header" => __("Type"),
                "align" => "left",
                "index" => "message_type",
                'width' => '150',
                'renderer' => Messagetype::class,
            ]
        );
        return parent::_prepareColumns();
    }
}
