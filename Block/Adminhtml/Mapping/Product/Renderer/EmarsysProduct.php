<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Mapping\Product\Renderer;

use Emarsys\Emarsys\Model\ResourceModel\Product\Collection;
use Emarsys\Emarsys\Model\ResourceModel\Product\CollectionFactory;
use Emarsys\Emarsys\Model\ResourceModel\Sync;
use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Magento\Backend\Helper\Data;
use Magento\Backend\Model\Session;
use Magento\Framework\DataObject;

class EmarsysProduct extends AbstractRenderer
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Collection
     */
    protected $collectionFactory;

    /**
     * @var Data
     */
    protected $backendHelper;

    /**
     * @var Sync
     */
    protected $syncResourceModel;

    /**
     * EmarsysProduct constructor.
     *
     * @param Session $session
     * @param CollectionFactory $collectionFactory
     * @param Sync $syncResourceModel
     * @param Data $backendHelper
     */
    public function __construct(
        Session $session,
        CollectionFactory $collectionFactory,
        Sync $syncResourceModel,
        Data $backendHelper
    ) {
        $this->session = $session;
        $this->collectionFactory = $collectionFactory;
        $this->syncResourceModel = $syncResourceModel;
        $this->backendHelper = $backendHelper;
    }

    /**
     * @param DataObject $row
     * @return string
     */
    public function render(DataObject $row)
    {
        global $colValue;
        $colValue = '';
        $attributeCode = $row->getData('attribute_code');
        $url = $this->backendHelper->getUrl('*/*/saveRow');
        $coulmnAttr = 'emarsys_attr_code';
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('magento_attr_code', $row->getAttributeCode());
        $session = $this->session->getData();

        $gridSessionStoreId = 1;
        if (isset($session['storeId'])) {
            $gridSessionStoreId = $session['storeId'];
            if ($gridSessionStoreId == 0) {
                $gridSessionStoreId = 1;
            }
            $collection->addFieldToFilter('store_id', $gridSessionStoreId);
        }

        $data = $collection->getData();
        foreach ($data as $col) {
            $colValue = $col['emarsys_attr_code'];
        }

        $emarsysProductAttributes = $this->syncResourceModel
            ->getAttributes('product', $gridSessionStoreId);
        $emarsysCustomProductAttributes = $this->syncResourceModel
            ->getAttributes('customproductattributes', $gridSessionStoreId);

        $html = '<select name="directions" class="admin__control-select"  style="width:200px;"'
            . ' onchange="changeValue('
            . '\'' . $url . '\', \'' . $attributeCode . '\', \'' . $coulmnAttr . '\', this.value);">'
            . '<option value="0">Please Select</option>';
        foreach ($emarsysProductAttributes as $prodValue) {
            $sel = '';
            if ($colValue == $prodValue['id']) {
                $sel = ' selected == selected';
            }
            $html .= '<option' . $sel . ' value="' . $prodValue['id'] . '">' . $prodValue['label'] . '</option>';
        }
        if ((count($emarsysProductAttributes) > 0) && (count($emarsysCustomProductAttributes) > 0)) {
            $html .= "<option disabled>----Custom Attributes----</option>'";
            foreach ($emarsysCustomProductAttributes as $customAttribute) {
                $sel = '';
                $customId = $customAttribute['id'] . "_CUSTOM";
                if ($colValue == $customId) {
                    $sel = 'selected == selected';
                }
                $html .= '<option' . $sel . ' value="' . $customId . '">'
                    . $customAttribute['description']
                    . '</option>';
            }
        }
        $html .= '</select>';

        return $html;
    }
}
