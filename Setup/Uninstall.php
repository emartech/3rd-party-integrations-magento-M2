<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Setup;

use Magento\Framework\Setup\UninstallInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class Uninstall implements UninstallInterface
{

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $setup->getConnection()->query('SET FOREIGN_KEY_CHECKS = 0;');

        $setup->getConnection()->query(
            'DROP TABLE IF EXISTS '
            . $setup->getTable('emarsys_async_events') . ','
            . $setup->getTable('emarsys_contact_field') . ','
            . $setup->getTable('emarsys_contact_field_option') . ','
            . $setup->getTable('emarsys_creditmemo_export_status') . ','
            . $setup->getTable('emarsys_cron_details') . ','
            . $setup->getTable('emarsys_custom_product_attributes') . ','
            . $setup->getTable('emarsys_customer_field_mapping') . ','
            . $setup->getTable('emarsys_customer_option_mapping') . ','
            . $setup->getTable('emarsys_emarsys_product_attributes') . ','
            . $setup->getTable('emarsys_event_mapping') . ','
            . $setup->getTable('emarsys_events') . ','
            . $setup->getTable('emarsys_log_cron_schedule') . ','
            . $setup->getTable('emarsys_log_details') . ','
            . $setup->getTable('emarsys_magento_customer_attributes') . ','
            . $setup->getTable('emarsys_magento_events') . ','
            . $setup->getTable('emarsys_option_mapping') . ','
            . $setup->getTable('emarsys_order_export_status') . ','
            . $setup->getTable('emarsys_order_field_mapping') . ','
            . $setup->getTable('emarsys_order_queue') . ','
            . $setup->getTable('emarsys_placeholders_mapping') . ','
            . $setup->getTable('emarsys_product_export') . ','
            . $setup->getTable('emarsys_product_mapping') . ','
            . $setup->getTable('emarsys_product_sync_temp') . ','
            . $setup->getTable('emarsys_suite2_queue') . ','
            . $setup->getTable('emarsys_variable_mapping') . ','
            . $setup->getTable('emarsys_product_export_data') . ','
            . $setup->getTable('emarsys_product_export_queue')
        );
        $setup->getConnection()->query('SET FOREIGN_KEY_CHECKS = 1;');

        $configPath = [
            '%emartech/emarsys_setting%',
            '%emartech/async%',
            '%emartech/webdav_setting%',
            '%emartech/ftp_settings%',
            '%emarsys_settings/emarsys_setting%',
            '%emarsys_settings/async%',
            '%emarsys_settings/webdav_setting%',
            '%emarsys_settings/ftp_settings%',
            '%contacts_synchronization/enable%',
            '%contacts_synchronization/emarsys_emarsys%',
            '%opt_in/optin_enable%',
            '%opt_in/subscription_checkout_process%',
            '%transaction_mail/transactionmail%',
            '%transaction_mail/schema_check%',
            '%smart_insight/api_settings%',
            '%smart_insight/smart_insight%',
            '%emarsys_predict/enable%',
            '%emarsys_predict/availability%',
            '%emarsys_predict/catalog_api_settings%',
            '%emarsys_predict/feed_export%',
            '%web_extend/recommended_product_pages%',
            '%web_extend/javascript_tracking%',
            '%logs/log_setting%',
            '%emarsys_product_sync/%',
            '%emarsys_schema_check/%',
            '%emarsys_smartinsight_sync_queue/%',
            '%emarsys_suite2/%',
            '%system/cron/emarsys/%',
        ];

        $setup->getConnection()->delete(
            $setup->getTable('core_config_data'),
            $setup->getConnection()->quoteInto('`path` in ?', $configPath)
        );

        $setup->endSetup();
    }
}
