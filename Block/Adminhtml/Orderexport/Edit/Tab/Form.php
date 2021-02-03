<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Orderexport\Edit\Tab;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\App\Request\Http;
use Magento\Backend\Block\Widget\Form\Generic;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;

/**
 * Class Form
 */
class Form extends Generic
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Http
     */
    protected $request;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * Form constructor.
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Http $request
     * @param EmarsysHelper $emarsysHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Http $request,
        EmarsysHelper $emarsysHelper,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
        $this->storeManager = $context->getStoreManager();
        $this->request = $request;
        $this->emarsysHelper = $emarsysHelper;
    }

    /**
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareForm()
    {
        $params = $this->request->getParams();
        $storeId = $params['store'];
        $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);
        $store = $this->storeManager->getStore($storeId);

        $form = $this->_formFactory->create();
        $this->setForm($form);
        $fieldset = $form->addFieldset("support_form", ["legend" => '']);
        $values = [];
        $values['customer'] = 'Customer';
        $values['subscriber'] = 'Subscriber';
        $smartInsightEnable = $store->getConfig(EmarsysHelper::XPATH_SMARTINSIGHT_ENABLED);
        if ($smartInsightEnable == 1) {
            $values['order'] = 'Order';
        }
        $productExportStatus = $store->getConfig(EmarsysHelper::XPATH_PREDICT_ENABLE_NIGHTLY_PRODUCT_FEED);
        if ($productExportStatus == 1 || $smartInsightEnable == 1) {
            $values['product'] = 'Product';
        }
        $fieldset->addField("entitytype", "select", [
            'label' => 'Export Entity Type',
            'name' => 'entity_type',
            'values' => $values,
            'value' => 'order',
            'onchange' => "bulkExport(this.value)",
            'style' => 'width:200px',
        ]);

        $fieldset->addField("fromdate", "date", [
            'label' => 'From',
            'name' => 'from_date',
            'style' => 'width:200px',
            'date_format' => $this->_localeDate->getDateFormat(\IntlDateFormatter::SHORT),
            'time_format' => $this->_localeDate->getTimeFormat(\IntlDateFormatter::SHORT),
        ]);

        $fieldset->addField("todate", "date", [
            'label' => 'To',
            'name' => 'to_date',
            'style' => 'width:200px',
            'date_format' => $this->_localeDate->getDateFormat(\IntlDateFormatter::SHORT),
            'time_format' => $this->_localeDate->getTimeFormat(\IntlDateFormatter::SHORT),
        ]);

        return parent::_prepareForm();
    }
}
