<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\Config;

use Exception;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class CronConfig extends Value
{
    /**
     * Cron string path
     */
    const CRON_STRING_PATH = 'emarsys_customer_sync_queue/schedule/cron_expr';

    /**
     * @var ValueFactory
     */
    protected $_configValueFactory;

    /**
     * @var string
     */
    protected $_runModelPath = '';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ValueFactory $configValueFactory
     * @param AbstractResource $resource
     * @param AbstractDb $resourceCollection
     * @param string $runModelPath
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ValueFactory $configValueFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        $runModelPath = '',
        array $data = []
    ) {
        $this->_runModelPath = $runModelPath;
        $this->_configValueFactory = $configValueFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     * @throws Exception
     */
    public function afterSave()
    {
        $time = $this->getData('groups/emarsys_emarsys/fields/background_runtime/value');
        $frequency = $this->getData('groups/emarsys_emarsys/fields/background_frequency/value');
        $cronExprArray = [];
        if ($frequency == 'Hourly') {
            $cronExprArray = [
                intval($time[1]), //Minute
                '*',
                '*',
                '*',
                '*',
            ];
        }
        if ($frequency == 'Daily') {
            $cronExprArray = [
                intval($time[1]), //Minute
                intval($time[0]), //Hour
                '*',
                '*',
                '*',
            ];
        }
        if ($frequency == 'Monthly') {
            $cronExprArray = [
                intval($time[1]), //Minute
                intval($time[0]), //Hour
                '1',
                '*',
                '*',
            ];
        }

        $cronExprString = join(' ', $cronExprArray);

        try {
            $this->_configValueFactory->create()->load(
                self::CRON_STRING_PATH,
                'path'
            )->setValue(
                $cronExprString
            )->setPath(
                self::CRON_STRING_PATH
            )->setScope(
                $this->getScope()
            )->setScopeId(
                $this->getScopeId()
            )->save();
        } catch (Exception $e) {
            throw new Exception(__('Can\'t save the cron expression.'));
        }

        return parent::afterSave();
    }
}
