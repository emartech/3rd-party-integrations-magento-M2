<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Mapping\Customer\Renderer;

use Emarsys\Emarsys\Model\ResourceModel\Customer;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Magento\Backend\Model\Session;
use Magento\Framework\DataObject;

class EmarsysCustomer extends AbstractRenderer
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
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * EmarsysCustomer constructor.
     *
     * @param Session $session
     * @param Customer $resourceModelCustomer
     * @param EmarsysHelper $emarsysHelper
     */
    public function __construct(
        Session $session,
        Customer $resourceModelCustomer,
        EmarsysHelper $emarsysHelper
    ) {
        $this->session = $session;
        $this->resourceModelCustomer = $resourceModelCustomer;
        $this->emarsysHelper = $emarsysHelper;
    }

    /**
     * @param DataObject $row
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function render(DataObject $row)
    {
        $storeId = $this->session->getStore();
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);

        $emarsysContactFields = $this->resourceModelCustomer->getEmarsysContactFields($storeId);
        $chkSelected = $this->resourceModelCustomer->checkAttributeUsed($row->getId(), $storeId);

        $html = '<select name="' . $row->getData('attribute_code_custom') . '"'
            . ' class="admin__control-select emaryscustomervalues" style="width:200px;">'
            . '<option value=" ">Please Select</option>';
        foreach ($emarsysContactFields as $field) {
            $selected = ($chkSelected && in_array($field['emarsys_field_id'], $chkSelected))
                ? ' selected = selected'
                : '';
            $html .= '<option value="' . $field['emarsys_field_id'] . '"' . $selected . '>'
                . $field['name']
                . '</option>';
        }
        $html .= '</select>';

        return $html;
    }
}
