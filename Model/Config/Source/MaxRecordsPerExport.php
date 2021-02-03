<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;

class MaxRecordsPerExport implements ArrayInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * MaxRecordsPerExport constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $maxRecordsPerExport = $this->scopeConfig->getValue(EmarsysHelper::XPATH_EMARSYS_SIEXPORT_MAX_RECORDS_OPT);
        $list = explode(',', $maxRecordsPerExport);
        $result = [];
        $result[] = ['value' => '', 'label' => 'Please Select'];

        foreach ($list as $item) {
            $result[] = [
                'label' => $item,
                'value' => $item,
            ];
        }

        return $result;
    }
}
