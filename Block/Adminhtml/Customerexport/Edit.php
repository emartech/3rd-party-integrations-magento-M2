<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Customerexport;

use Magento\Framework\Exception\LocalizedException;

class Edit extends \Emarsys\Emarsys\Block\Adminhtml\Export\Edit
{
    /**
     * Support edit block
     *
     * @return void
     * @throws LocalizedException
     */
    protected function _construct()
    {
        $this->_controller = 'adminhtml_customerexport';
        $storeId = $this->getRequest->getParam('store');
        $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId);
        $url = $this->getUrl("emarsys_emarsys/customerexport/customerExport", ["storeId" => $storeId]);

        parent::_construct();

        $this->buttonList->remove('back');
        $this->buttonList->remove('save');
        $this->buttonList->remove('reset');
        if (isset($storeId)) {
            $this->buttonList->add(
                'showreport',
                [
                    'label' => __("Export"),
                    'class' => 'save primary',
                    'onclick' => "bulkExportData('" . $url . "')",
                ]
            );
        }
    }
}
