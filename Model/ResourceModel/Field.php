<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\ResourceModel;

use Emarsys\Emarsys\Model\ContactFieldOption as ModelContactFieldOption;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class Field
 */
class Field extends AbstractDb
{
    /**
     * @var StoreRepositoryInterface
     */
    protected $storeRepository;

    /**
     * @var ContactFieldOption
     */
    protected $contactFieldOption;

    /**
     * Field constructor.
     *
     * @param Context $context
     * @param StoreRepositoryInterface $storeRepository
     * @param ModelContactFieldOption $contactFieldOption
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        StoreRepositoryInterface $storeRepository,
        ModelContactFieldOption $contactFieldOption,
        $connectionName = null
    ) {
        $this->storeRepository = $storeRepository;
        $this->contactFieldOption = $contactFieldOption;
        parent::__construct($context, $connectionName);
    }

    /**
     * Define main table
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('emarsys_option_mapping', 'id');
    }

    /**
     * Checking count of the mapping table
     *
     * @param $storeId
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function checkOptionsMapping($storeId)
    {
        $select = $this->getConnection()
            ->select()
            ->from($this->getMainTable(), 'count(*)')
            ->where("store_id = ?", $storeId);

        return $this->getConnection()->fetchOne($select);
    }

    /**
     * truncate the mapping table
     * @param $storeId
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function truncateMappingTable($storeId)
    {
        $this->getConnection()->delete(
            $this->getMainTable(),
            $this->getConnection()->quoteInto("store_id = ?", $storeId)
        );
    }

    /**
     * @param array $fieldOptions
     * @param $storeId
     * @return bool
     */
    public function updateOptionSchema($fieldOptions = [], $storeId)
    {
        $this->getConnection()->delete(
            $this->getTable("emarsys_contact_field_option"),
            $this->getConnection()->quoteInto("store_id = ?", $storeId)
        );
        if (count($fieldOptions) > 0) {
            foreach ($fieldOptions as $fieldId => $arrOptions) {
                foreach ($arrOptions as $option) {
                    $data = ['field_id' => $fieldId, 'option_id' => $option['id'], 'option_name' => $option['choice'], 'store_id' => $storeId];
                    $data = $this->_prepareDataForTable(
                        new \Magento\Framework\DataObject($data),
                        $this->getTable("emarsys_contact_field_option")
                    );
                    $this->getConnection()->insert($this->getTable("emarsys_contact_field_option"), $data);
                }
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $storeId
     * @return $this
     */
    public function getEmarsysFieldOption($storeId)
    {
        $contactFieldColl = $this->contactFieldOption->getCollection()
            ->addFieldToSelect('option_id')
            ->addFieldToSelect('option_name');

        $contactFieldColl->join(
            ['ecf' => $this->getTable('emarsys_contact_field')],
            'ecf.emarsys_field_id = main_table.field_id and ecf.store_id = main_table.store_id',
            ['ecf.name', 'ecf.emarsys_field_id', 'ecf.type']
        )->addFieldToFilter('main_table.store_id', ['eq' => $storeId]);

        return $contactFieldColl;
    }

    /**
     * @param $magentoOptionId
     * @param $emarsysOptionId
     * @param $emarsysFieldId
     * @param $storeId
     * @return string
     */
    public function checkSelectedOption($magentoOptionId, $emarsysOptionId, $emarsysFieldId, $storeId)
    {
        $select = $this->getConnection()
            ->select()
            ->from(['eom' => $this->getTable('emarsys_option_mapping')], ['assigned' => 'count(*)'])
            ->join(
                ['eao' => $this->getTable('eav_attribute_option')],
                'eao.option_id = eom.magento_option_id',
                []
            )
            ->where('eao.option_id = ?', $magentoOptionId)
            ->where('eom.emarsys_option_id = ?', $emarsysOptionId)
            ->where('eom.emarsys_field_id = ?', $emarsysFieldId)
            ->where('eom.store_id = ?', $storeId);

        return  $this->getConnection()->fetchOne($select);
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getEmarsysOptionCount($storeId)
    {
        $contactFieldColl = $this->contactFieldOption->getCollection()
            ->addFieldToSelect('*')
            ->addFieldToFilter('store_id', ['eq' => $storeId])
            ->count();

        return $contactFieldColl;
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getRecommendedFieldAttribute($storeId)
    {
        return [
            '1' => '1-5', //male-gender
            '2' => '2-5', //female-gender
        ];
    }
}
