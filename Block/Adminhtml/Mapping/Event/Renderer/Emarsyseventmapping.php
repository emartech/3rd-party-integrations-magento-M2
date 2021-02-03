<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Mapping\Event\Renderer;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Emarsys\Emarsys\Model\ResourceModel\Emarsysevents\CollectionFactory as EmarsysEventCollectionFactory;
use Emarsys\Emarsys\Model\ResourceModel\Emarsysmagentoevents\CollectionFactory as MagentoEventsCollectionFactory;
use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Magento\Backend\Model\Session;
use Magento\Framework\DataObject;
use Magento\Framework\UrlInterface;

class Emarsyseventmapping extends AbstractRenderer
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
     * @var EmarsysEventCollectionFactory
     */
    protected $emarsysEventCollection;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var MagentoEventsCollectionFactory
     */
    protected $magentoEventsCollection;

    /**
     * @var UrlInterface
     */
    protected $urlInterface;

    /**
     * Emarsyseventmapping constructor.
     *
     * @param Session $session
     * @param CustomerCollectionFactory $collectionFactory
     * @param UrlInterface $urlInterface
     * @param EmarsysHelper $emarsysHelper
     * @param MagentoEventsCollectionFactory $magentoEventsCollection
     * @param EmarsysEventCollectionFactory $emarsysEventCollection
     */
    public function __construct(
        Session $session,
        CustomerCollectionFactory $collectionFactory,
        UrlInterface $urlInterface,
        EmarsysHelper $emarsysHelper,
        MagentoEventsCollectionFactory $magentoEventsCollection,
        EmarsysEventCollectionFactory $emarsysEventCollection
    ) {
        $this->session = $session;
        $this->collectionFactory = $collectionFactory;
        $this->emarsysEventCollection = $emarsysEventCollection;
        $this->emarsysHelper = $emarsysHelper;
        $this->urlInterface = $urlInterface;
        $this->magentoEventsCollection = $magentoEventsCollection;
    }

    /**
     * @param DataObject $row
     * @return string
     */
    public function render(DataObject $row)
    {
        $id = $row->getId();
        $magentoEventName = $this->magentoEventsCollection->create()
            ->addFieldToFilter('id', $id)
            ->getFirstItem()
            ->getData('magento_event');

        $emarsysEventName = trim(str_replace(" ", "_", strtolower($magentoEventName)));
        $gridSessionData = $this->session->getMappingGridData();

        $emarsysEvents = $this->emarsysEventCollection->create()->addFieldToFilter(
            'store_id',
            ['eq' => $this->session->getStoreId()]
        );
        $dbEvents = $emarsysEvents->getAllIds();

        $readOnly = '';
        if ($this->emarsysHelper->isReadonlyMagentoEventId($id)) {
            $readOnly .= 'disabled = disabled ';
        }

        $html = '<select ' . $readOnly . 'name="directions" style="width:200px;"'
            . ' onchange="changeEmarsysValue('
            . '\'' . $this->urlInterface->getUrl('*/*/changeValue') . '\','
            . ' this.value,'
            . ' \'' . $id . '\','
            . ' \'' . $id . '\');">'
            . '<option value="0">Please Select</option>';

        $gridSessionData[$id]['magento_event_id'] = $id;
        foreach ($emarsysEvents as $emarsysEvent) {
            $sel = '';

            if (($row->getEmarsysEventId() == $emarsysEvent->getId())
                || (($emarsysEventName == $emarsysEvent->getEmarsysEvent()) && ($row->getEmarsysEventId() == null))
                || (($emarsysEventName == $emarsysEvent->getEmarsysEvent())
                    && ($row->getEmarsysEventId() != null) && !in_array($row->getEmarsysEventId(), $dbEvents))
            ) {
                $sel .= 'selected = selected';
                $gridSessionData[$id]['emarsys_event_id'] = $emarsysEvent->getId();
                $gridSessionData[$id]['recommended'] = 1;
            }
            $html .= '<option ' . $sel . ' value="' . $emarsysEvent->getId() . '">'
                . $emarsysEvent->getEmarsysEvent()
                . '</option>';
        }
        $html .= '</select>';

        $this->session->setMappingGridData($gridSessionData);
        return $html;
    }
}
