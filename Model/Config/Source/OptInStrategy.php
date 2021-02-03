<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\Config\Source;

class OptInStrategy implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'singleOptIn', 'label' => 'Single-Opt-In'],
            ['value' => 'doubleOptIn', 'label' => 'Double-Opt-In'],
        ];
    }
}
