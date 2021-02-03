<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\Config\Source;

class AsyncFrequency implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => '1'],
            ['value' => '2', 'label' => '2'],
            ['value' => '3', 'label' => '3'],
            ['value' => '4', 'label' => '4'],
            ['value' => '5', 'label' => '5'],
        ];
    }
}
