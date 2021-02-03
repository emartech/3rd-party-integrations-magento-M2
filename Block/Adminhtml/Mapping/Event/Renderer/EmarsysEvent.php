<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Mapping\Event\Renderer;

use Magento\Framework\DataObject;
use Magento\Backend\Model\Session;
use Magento\Backend\Helper\Data as BackendHelper;
use Magento\Framework\UrlInterface;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Model\ResourceModel\Emarsysevents\CollectionFactory as EmarsysEventsCollectionFactory;
use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;

class EmarsysEvent extends AbstractRenderer
{
    /**
     * @var \Magento\Backend\Model\Session
     */
    protected $session;

    /**
     * @var
     */
    protected $collectionFactory;

    /**
     * @var BackendHelper
     */
    protected $backendHelper;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var EmarsysEventsCollectionFactory
     */
    protected $emarsysEventCollection;

    /**
     * EmarsysEvent constructor.
     *
     * @param Session $session
     * @param BackendHelper $backendHelper
     * @param UrlInterface $urlInterface
     * @param EmarsysHelper $emarsysHelper
     * @param EmarsysEventsCollectionFactory $EmarsyseventCollection
     */
    public function __construct(
        Session $session,
        BackendHelper $backendHelper,
        UrlInterface $urlInterface,
        EmarsysHelper $emarsysHelper,
        EmarsysEventsCollectionFactory $EmarsyseventCollection
    ) {
        $this->session = $session;
        $this->backendHelper = $backendHelper;
        $this->emarsysEventCollection = $EmarsyseventCollection;
        $this->emarsysHelper = $emarsysHelper;
        $this->_urlInterface = $urlInterface;
    }

    /**
     * @param DataObject $row
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function render(DataObject $row)
    {
        $storeId = $this->session->getData('store');
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);
        $url = $this->_urlInterface->getUrl('*/*/changeValue');
        $params = ['mapping_id' => $row->getId(), 'store' => $storeId];
        $placeHolderUrl = $this->backendHelper->getUrl("*/*/placeholders", $params);
        $placeholderJsonRequestUrl = $this->backendHelper->getUrl(
            "*/*/placeholderjson",
            [
                'mapping_id' => $row->getId(),
                'store_id' => $storeId,
            ]
        );
        $emarsysEvents = $this->emarsysEventCollection->create()->addFieldToFilter('store_id', ['eq' => $storeId]);
        $readOnly = '';
        $buttonClass = '';

        if ($this->emarsysHelper->isReadonlyMagentoEventId($row->getId())) {
            $readOnly .= ' disabled = disabled';
            $buttonClass = ' disabled';
        }

        $html = '<select class="admin__control-select mapping-select" ' . $readOnly
            . ' name="directions"  style="width:200px;"'
            . ' onchange="changeEmarsysValue('
            . '\'' . $url . '\', this.value, \'' . $row->getId() . '\', \'' . $row->getId() . '\');">'
            . '<option value="0">Please Select</option>';

        foreach ($emarsysEvents as $emarsysEvent) {
            $selected = ($row->getData('emarsys_event_id') == $emarsysEvent->getId())
                ? ' selected = selected'
                : '';
            $html .= '<option value="' . $emarsysEvent->getId() . '"' . $selected . '>'
                . $emarsysEvent->getEmarsysEvent()
                . '</option>';
        }

        $html .= '</select>';
        $html .= '&nbsp;&nbsp;&nbsp;<button ' . $buttonClass . ' type="button" class="scalable task form-button '
            . $buttonClass . '" name="json" id="json"'
            . ' onclick = "openMyPopup(\'' . $placeholderJsonRequestUrl . '\');" >JSON Request</button >';
        $html .= ' &nbsp;&nbsp;<a class="scalable task form-button" name="placeholders"'
            . ' id ="placeholders"  href="' . $placeHolderUrl . '">Placeholders</a>';

        return $html;
    }
}
