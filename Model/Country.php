<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model;

use Magento\Framework\Model\AbstractExtensibleModel;
use Emarsys\Emarsys\Api\Data\CountryInterface;

/**
 * Class Field
 */
class Country extends AbstractExtensibleModel implements CountryInterface
{
    const ID = 'id';
    const CHOICE = 'choice';
    const BIT_POSITION = 'bit_position';
    const WEBSITE_ID = 'website_id';
    const MAGENTO_ID = 'magento_id';

    /**
     * constructor
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init(ResourceModel\Country::class);
    }

    /**
     * @return string
     */
    public function getChoice()
    {
        return $this->_getData(self::CHOICE);
    }

    /**
     * @param string|null $choice
     * @return void
     */
    public function setChoice($choice)
    {
        $this->setData(self::CHOICE, $choice);
    }

    /**
     * @return string|null
     */
    public function getBitPosition()
    {
        return $this->_getData(self::BIT_POSITION);
    }

    /**
     * @param string|null $bitPosition
     * @return void
     */
    public function setBitPosition($bitPosition)
    {
        $this->setData(self::BIT_POSITION, $bitPosition);
    }

    /**
     * @return int
     */
    public function getWebsiteId()
    {
        return $this->_getData(self::WEBSITE_ID);
    }

    /**
     * @param int $websiteId
     * @return void
     */
    public function setWebsiteId($websiteId)
    {
        $this->setData(self::WEBSITE_ID, $websiteId);
    }

    /**
     * @return string
     */
    public function getMagentoId()
    {
        return $this->_getData(self::MAGENTO_ID);
    }

    /**
     * @param string $magentoId
     * @return void
     */
    public function setMagentoId($magentoId)
    {
        $this->setData(self::MAGENTO_ID, $magentoId);
    }
}
