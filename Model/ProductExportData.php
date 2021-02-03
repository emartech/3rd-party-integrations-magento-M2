<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model;

use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * Class Field
 */
class ProductExportData extends AbstractExtensibleModel
{
    const ID = 'id';
    const DATA = 'export_data';

    /**
     * constructor
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init(ResourceModel\ProductExportData::class);
    }

    /**
     * @return int
     */
    public function getExportData()
    {
        return $this->_getData(self::DATA);
    }

    /**
     * @param mixed $data
     * @return void
     */
    public function setExportData($data)
    {
        $this->setData(self::DATA, $data);
    }
}
