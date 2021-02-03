<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

/**
 * Class UpgradeSchema
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    const EMARSYS_PRODUCT_EXPORT = 'emarsys_product_export';

    const EMARSYS_CRON_SUPPORT_TABLE = 'emarsys_cron_details';
    const MAGENTO_CRON_SCHEDULE = 'cron_schedule';

    const EMARSYS_LOG_DETAILS = 'emarsys_log_details';
    const EMARSYS_LOG_CRON_SCHEDULE = 'emarsys_log_cron_schedule';

    const EMARSYS_ASYNC_EVENTS = 'emarsys_async_events';

    const EMARSYS_MAGENTO_EVENTS = 'emarsys_magento_events';

    const EMARSYS_COUNTRY_MAPPING = 'emarsys_country_mapping';

    const EMARSYS_PRODUCT_EXPORT_DATA = 'emarsys_product_export_data';
    const EMARSYS_PRODUCT_EXPORT_QUEUE = 'emarsys_product_export_queue';

    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        if (version_compare($context->getVersion(), '1.0.6', '<')) {
            $this->createEmarsysCronTable($setup);
            $this->removeDataFromCoreConfigData($setup);
        }

        if (version_compare($context->getVersion(), '1.0.7', '<')) {
            $tableName = $setup->getTable(self::EMARSYS_PRODUCT_EXPORT);
            $connection = $setup->getConnection();
            if (!$connection->isTableExists($tableName)) {
                $table = $connection
                    ->newTable($tableName)
                    ->addColumn(
                        'entity_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        [
                            'identity' => true,
                            'unsigned' => true,
                            'nullable' => false,
                            'primary' => true,
                            'auto_increment' => true,
                        ],
                        'Product Id'
                    )->addColumn(
                        'params',
                        \Magento\Framework\DB\Ddl\Table::TYPE_BLOB,
                        '128k',
                        [],
                        'Product Params'
                    )
                    ->setComment('Catalog Product Export');
                $setup->getConnection()->createTable($table);
            }
        }

        if (version_compare($context->getVersion(), '1.0.13', '<')) {
            $connection = $setup->getConnection();
            $cronDetailsTable = $setup->getTable(self::EMARSYS_CRON_SUPPORT_TABLE);
            if ($connection->isTableExists($cronDetailsTable)) {
                $connection->addForeignKey(
                    $setup->getFkName(
                        self::EMARSYS_CRON_SUPPORT_TABLE,
                        'schedule_id',
                        self::MAGENTO_CRON_SCHEDULE,
                        'schedule_id'
                    ),
                    $cronDetailsTable,
                    'schedule_id',
                    $setup->getTable('cron_schedule'),
                    'schedule_id',
                    \Magento\Framework\DB\Ddl\Table::ACTION_CASCADE
                );
            }

            $emarsysLogDetailsTable = $setup->getTable(self::EMARSYS_LOG_DETAILS);
            $emarsysLogCronScheduleTable = $setup->getTable(self::EMARSYS_LOG_CRON_SCHEDULE);

            if ($connection->isTableExists($emarsysLogDetailsTable)
                && $connection->isTableExists($emarsysLogCronScheduleTable)
            ) {
                $connection->truncateTable($emarsysLogDetailsTable);
                $connection->truncateTable($emarsysLogCronScheduleTable);

                $connection->changeColumn(
                    $emarsysLogDetailsTable,
                    'log_exec_id',
                    'log_exec_id',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        'unsigned' => true,
                        'nullable' => false,
                        'length' => 10,
                        'comment' => 'emarsys_log_cron_schedule id',
                    ]
                );

                $connection->addIndex(
                    $emarsysLogDetailsTable,
                    $setup->getIdxName($emarsysLogDetailsTable, 'log_exec_id'),
                    ['log_exec_id']
                );

                $connection->addForeignKey(
                    $setup->getFkName(
                        $emarsysLogDetailsTable,
                        'log_exec_id',
                        $emarsysLogCronScheduleTable,
                        'id'
                    ),
                    $emarsysLogDetailsTable,
                    'log_exec_id',
                    $emarsysLogCronScheduleTable,
                    'id'
                );
            }
        }

        if (version_compare($context->getVersion(), '1.0.16', '<')) {
            $connection = $setup->getConnection();
            $emarsysAsyncEventsTable = $setup->getTable(self::EMARSYS_ASYNC_EVENTS);

            if (!$connection->isTableExists($emarsysAsyncEventsTable)) {
                $table = $connection
                    ->newTable($emarsysAsyncEventsTable)
                    ->addColumn(
                        'entity_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        [
                            'identity' => true,
                            'unsigned' => true,
                            'nullable' => false,
                            'primary' => true,
                            'auto_increment' => true,
                        ],
                        'Entity Id'
                    )->addColumn(
                        'website_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        ['default' => 0, 'nullable' => false],
                        'Website Id'
                    )->addColumn(
                        'endpoint',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        255,
                        ['default' => null, 'nullable' => false],
                        'Endpoint'
                    )->addColumn(
                        'email',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        255,
                        ['default' => null, 'nullable' => false],
                        'Email'
                    )->addColumn(
                        'customer_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        ['default' => null, 'nullable' => true],
                        'Customer Id'
                    )->addColumn(
                        'subscriber_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        ['default' => null, 'nullable' => true],
                        'Subscriber Id'
                    )->addColumn(
                        'request_body',
                        \Magento\Framework\DB\Ddl\Table::TYPE_BLOB,
                        '128k',
                        [],
                        'Request Body'
                    )->addColumn(
                        'updated_at',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                        null,
                        [],
                        'Update Time'
                    )->setComment('Emarsys Async Events');
                $setup->getConnection()->createTable($table);

                $connection->addIndex(
                    $emarsysAsyncEventsTable,
                    $setup->getIdxName($emarsysAsyncEventsTable, 'website_id'),
                    ['website_id']
                );
            } else {
                $connection->changeColumn(
                    $setup->getTable(self::EMARSYS_ASYNC_EVENTS),
                    'request_body',
                    'request_body',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_BLOB,
                        'length' => '128k',
                        'comment' => 'Request Body',
                    ]
                );
            }
            $connection->changeColumn(
                $setup->getTable(self::EMARSYS_PRODUCT_EXPORT),
                'params',
                'params',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_BLOB,
                    'length' => '128k',
                    'comment' => 'Product Params',
                ]
            );
        }

        if (version_compare($context->getVersion(), '1.0.27', '<')) {
            $connection = $setup->getConnection();
            $emarsysMagentoEventsTable = $setup->getTable(self::EMARSYS_MAGENTO_EVENTS);

            if ($connection->isTableExists($emarsysMagentoEventsTable)) {
                $connection->addColumn(
                    $emarsysMagentoEventsTable,
                    'template_id',
                    [
                        'type' => Table::TYPE_TEXT,
                        'length' => 255,
                        'nullable' => true,
                        'comment' => 'Magento Template Id'
                    ]
                );
            }
        }

        if (version_compare($context->getVersion(), '1.0.30', '<')) {
            $connection = $setup->getConnection();
            $emarsysCountryMappingTable = $setup->getTable(self::EMARSYS_COUNTRY_MAPPING);
            if (!$connection->isTableExists($emarsysCountryMappingTable)) {
                $table = $connection
                    ->newTable($emarsysCountryMappingTable)
                    ->addColumn(
                        'id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        [
                            'identity' => true,
                            'unsigned' => true,
                            'nullable' => false,
                            'primary' => true,
                            'auto_increment' => true,
                        ],
                        'Id'
                    )->addColumn(
                        'choice',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        255,
                        ['default' => null, 'nullable' => true],
                        'Choice'
                    )->addColumn(
                        'bit_position',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        255,
                        ['default' => null, 'nullable' => true],
                        'Bit Position'
                    )->addColumn(
                        'magento_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        255,
                        ['default' => null, 'nullable' => true],
                        'Magento Country Id'
                    )->addColumn(
                        'website_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        ['default' => 0, 'nullable' => false],
                        'Website Id'
                    )->setComment('Emarsys Country Mapping');
                $setup->getConnection()->createTable($table);
            }

            $emarsysProductExportDataTable = $setup->getTable(self::EMARSYS_PRODUCT_EXPORT_DATA);
            if (!$connection->isTableExists($emarsysProductExportDataTable)) {
                $table = $connection
                    ->newTable($emarsysProductExportDataTable)
                    ->addColumn(
                        'id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        [
                            'identity' => true,
                            'unsigned' => true,
                            'nullable' => false,
                            'primary' => true,
                            'auto_increment' => true,
                        ],
                        'Website Id'
                    )->addColumn(
                        'export_data',
                        \Magento\Framework\DB\Ddl\Table::TYPE_BLOB,
                        '128k',
                        [],
                        'Export Params'
                    )->setComment('Emarsys Product Export Params');
                $setup->getConnection()->createTable($table);
            }

            $emarsysProductExportQueueTable = $setup->getTable(self::EMARSYS_PRODUCT_EXPORT_QUEUE);
            if (!$connection->isTableExists($emarsysProductExportQueueTable)) {
                $table = $connection
                    ->newTable($emarsysProductExportQueueTable)
                    ->addColumn(
                        'id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        [
                            'identity' => true,
                            'unsigned' => true,
                            'nullable' => false,
                            'primary' => true,
                            'auto_increment' => true,
                        ],
                        'Id'
                    )->addColumn(
                        'from',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        ['default' => 0, 'nullable' => false],
                        'From'
                    )->addColumn(
                        'to',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        ['default' => 0, 'nullable' => false],
                        'to'
                    )->addColumn(
                        'status',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        255,
                        ['default' => 'ready', 'nullable' => false],
                        'Status'
                    )->setComment('Emarsys Product Export Queue');
                $setup->getConnection()->createTable($table);
            }

            $setup->endSetup();
        }
    }

    /**
     * @param $setup
     */
    protected function removeDataFromCoreConfigData($setup)
    {
        $paths = [
            'crontab/default/jobs/emarsys_sync/schedule/cron_expr',
            'crontab/default/jobs/shcema_check/schedule/cron_expr',
            'crontab/default/jobs/emarsys_smartinsight_sync/schedule/cron_expr',
            'crontab/default/jobs/emarsys_productexport_sync/schedule/cron_expr',
        ];

        foreach ($paths as $path) {
            $setup->getConnection()
                ->delete($setup->getTable('core_config_data'), "path='$path'");
        }
    }

    /**
     * @param SchemaSetupInterface $setup
     * @throws \Zend_Db_Exception
     */
    protected function createEmarsysCronTable(SchemaSetupInterface $setup)
    {
        /**
         * Create table 'emarsys_cron_details'
         */
        $table = $setup->getConnection()
            ->newTable($setup->getTable(self::EMARSYS_CRON_SUPPORT_TABLE))
            ->addColumn(
                'schedule_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Schedule Id'
            )
            ->addColumn(
                'params',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                '64k',
                [],
                'Params'
            )
            ->setComment('Emarsys Cron Support Table');
        $setup->getConnection()->createTable($table);
    }
}
