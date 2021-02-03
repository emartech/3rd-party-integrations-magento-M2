<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * Class UpgradeSchema
 */
class UpgradeData implements UpgradeDataInterface
{

    /**
     * @var ProductMetadataInterface $productMetadata
     */
    protected $productMetadata;

    /**
     * InstallData constructor.
     *
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        ProductMetadataInterface $productMetadata
    ) {
        $this->productMetadata = $productMetadata;
    }

    /**
     * {@inheritdoc}
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), "1.0.14", "<")) {
            $tableName = $setup->getTable('emarsys_customer_field_mapping');
            $connection = $setup->getConnection();
            if ($connection->isTableExists($tableName)) {
                $connection->delete(
                    $tableName,
                    ['emarsys_contact_field in (?)' => [0, 27, 28, 29, 30, 33, 34, 36, 47, 48]]
                );
            }

            $tableName = $setup->getTable('core_config_data');
            $connection->update(
                $tableName,
                ['path' => new \Zend_Db_Expr('(TRIM(LEADING "crontab/default/jobs/" FROM `path`))')],
                $connection->quoteInto('`path` like ?', '%crontab/default/jobs/emarsys%')
            );
            $connection->update(
                $tableName,
                ['value' => 1],
                $connection->quoteInto('path = ?', 'contacts_synchronization/emarsys_emarsys/realtime_sync')
            );
        }

        if (version_compare($context->getVersion(), "1.0.15", "<")) {
            $tableName = $setup->getTable('emarsys_magento_events');
            $setup->getConnection()->delete(
                $tableName,
                ['config_path = ?' => 'admin/emails/forgot_email_template']
            );
        }

        if (version_compare($context->getVersion(), '1.0.27', '<')) {
            $connection = $setup->getConnection();
            $tableName = $setup->getTable('emarsys_magento_events');

            $magentoEvents = [
                [
                    'currency_import_error_email_template',
                    'currency/import/error_email_template',
                    'Currency Update Warnings',
                ],
                ['customer_create_account_email_template', 'customer/create_account/email_template', 'New Account'],
                [
                    'customer_create_account_email_no_password_template',
                    'customer/create_account/email_no_password_template',
                    'New Account Without Password',
                ],
                [
                    'customer_create_account_email_confirmation_template',
                    'customer/create_account/email_confirmation_template',
                    'New Account Confirmation Key',
                ],
                [
                    'customer_create_account_email_confirmed_template',
                    'customer/create_account/email_confirmed_template',
                    'New Account Confirmed',
                ],
                [
                    'customer_password_forgot_email_template',
                    'customer/password/forgot_email_template',
                    'Forgot Password',
                ],
                [
                    'customer_password_remind_email_template',
                    'customer/password/remind_email_template',
                    'Remind Password',
                ],
                [
                    'customer_password_reset_password_template',
                    'customer/password/reset_password_template',
                    'Reset Password',
                ],
                [
                    'customer_account_information_change_email_template',
                    'customer/account_information/change_email_template',
                    'Change Email',
                ],
                [
                    'customer_account_information_change_email_and_password_template',
                    'customer/account_information/change_email_and_password_template',
                    'Change Email and Password',
                ],
                ['admin_emails_forgot_email_template', 'admin/emails/forgot_email_template', 'Forgot Admin Password'],
                [
                    'admin_emails_user_notification_template',
                    'admin/emails/user_notification_template',
                    'User Notification',
                ],
                [
                    'admin_emails_new_user_notification_template',
                    'admin/emails/new_user_notification_template',
                    'New User Notification',
                ],
                ['sales_email_order_template', 'sales_email/order/template', 'New Order'],
                ['sales_email_order_guest_template', 'sales_email/order/guest_template', 'New Order for Guest'],
                ['sales_email_order_comment_template', 'sales_email/order_comment/template', 'Order Update'],
                [
                    'sales_email_order_comment_guest_template',
                    'sales_email/order_comment/guest_template',
                    'Order Update for Guest',
                ],
                ['sales_email_invoice_template', 'sales_email/invoice/template', 'New Invoice'],
                ['sales_email_invoice_guest_template', 'sales_email/invoice/guest_template', 'New Invoice for Guest'],
                ['sales_email_invoice_comment_template', 'sales_email/invoice_comment/template', 'Invoice Update'],
                [
                    'sales_email_invoice_comment_guest_template',
                    'sales_email/invoice_comment/guest_template',
                    'Invoice Update for Guest',
                ],
                ['sales_email_creditmemo_template', 'sales_email/creditmemo/template', 'New Credit Memo'],
                [
                    'sales_email_creditmemo_guest_template',
                    'sales_email/creditmemo/guest_template',
                    'New Credit Memo for Guest',
                ],
                [
                    'sales_email_creditmemo_comment_template',
                    'sales_email/creditmemo_comment/template',
                    'Credit Memo Update',
                ],
                [
                    'sales_email_creditmemo_comment_guest_template',
                    'sales_email/creditmemo_comment/guest_template',
                    'Credit Memo Update for Guest',
                ],
                ['sales_email_shipment_template', 'sales_email/shipment/template', 'New Shipment'],
                [
                    'sales_email_shipment_guest_template',
                    'sales_email/shipment/guest_template',
                    'New Shipment for Guest',
                ],
                ['sales_email_shipment_comment_template', 'sales_email/shipment_comment/template', 'Shipment Update'],
                [
                    'sales_email_shipment_comment_guest_template',
                    'sales_email/shipment_comment/guest_template',
                    'Shipment Update for Guest',
                ],
                ['wishlist_email_email_template', 'wishlist/email/email_template', 'Wish List Sharing'],
                [
                    'catalog_productalert_email_stock_template',
                    'catalog/productalert/email_stock_template',
                    'Stock Alert',
                ],
                [
                    'catalog_productalert_email_price_template',
                    'catalog/productalert/email_price_template',
                    'Price Alert',
                ],
                [
                    'catalog_productalert_cron_error_email_template',
                    'catalog/productalert_cron/error_email_template',
                    'Cron Error Warning',
                ],
                ['contact_email_email_template', 'contact/email/email_template', 'Contact Form'],
                ['design_email_header_template', 'design/email/header_template', 'Header'],
                ['design_email_footer_template', 'design/email/footer_template', 'Footer'],
                [
                    'newsletter_subscription_confirm_email_template',
                    'newsletter/subscription/confirm_email_template',
                    'Subscription Confirmation',
                ],
                [
                    'newsletter_subscription_success_email_template',
                    'newsletter/subscription/success_email_template',
                    'Subscription Success',
                ],
                [
                    'newsletter_subscription_un_email_template',
                    'newsletter/subscription/un_email_template',
                    'Unsubscription Success',
                ],
                ['sendfriend_email_template', 'sendfriend/email/template', 'Send Product Link to Friend'],
                [
                    'sitemap_generate_error_email_template',
                    'sitemap/generate_error_email/template',
                    'Sitemap Generation Warnings',
                ],
                ['amazon_payments_auth_soft_decline', '', 'Soft-declined Authorization'],
                ['amazon_payments_auth_hard_decline', '', 'Hard-declined Authorization'],
                ['error_log_email_template', '', 'Error Email'],
                ['help_email_template_id', '', 'Emarsys Support Email'],
                ['sales_email_temando_pickup_order_template', '', 'Pickup Order New'],
                ['sales_email_temando_pickup_order_guest_template', '', 'Pickup Order New Guest'],
                ['sales_email_temando_pickup_ready_template', '', 'Pickup Ready'],
                ['sales_email_temando_pickup_collected_template', '', 'Pickup Collected'],
                ['sales_email_temando_pickup_canceled_template', '', 'Pickup Canceled'],
                ['sales_email_shipment_cancel_template', 'sales_email/shipment/cancel_template', 'Shipment Cancelled'],
                [
                    'sales_email_shipment_cancel_guest_template',
                    'sales_email/shipment/cancel_guest_template',
                    'Shipment Cancelled for Guest',
                ],
            ];

            if ($this->productMetadata->getEdition() == 'Enterprise') {
                $magentoEventsEnterprise = [
                    ['checkout_payment_failed_template', 'checkout/payment_failed/template', 'Payment Failed'],
                    ['giftcard_email_template', 'giftcard/email/template', 'Gift Card(s) Purchase'],
                    [
                        'customer_magento_customerbalance_email_template',
                        'customer/magento_customerbalance/email_template',
                        'Store Credit Update',
                    ],
                    [
                        'giftcard_giftcardaccount_email_template',
                        'giftcard/giftcardaccount/email_template',
                        'Gift Card Code/Balance',
                    ],
                    [
                        'magento_giftregistry_owner_email_template',
                        'magento_giftregistry/owner/email_template',
                        'New Registry',
                    ],
                    [
                        'magento_giftregistry_sharing_email_template',
                        'magento_giftregistry/sharing/email_template',
                        'Registry Sharing',
                    ],
                    [
                        'magento_giftregistry_update_email_template',
                        'magento_giftregistry/update/email_template',
                        'Registry Update',
                    ],
                    ['magento_invitation_email_template', 'magento_invitation/email/template', 'Customer Invitation'],
                    [
                        'magento_reminder_email_template',
                        'magento_reminder/email/template',
                        'Promotion Notification/Reminder',
                    ],
                    [
                        'magento_reward_notification_balance_update_template',
                        'magento_reward/notification/balance_update_template',
                        'Balance Update',
                    ],
                    [
                        'magento_reward_notification_expiry_warning_template',
                        'magento_reward/notification/expiry_warning_template',
                        'Points Expiry Warning',
                    ],
                    ['sales_email_magento_rma_template', 'sales_email/magento_rma/template', 'New RMA'],
                    [
                        'sales_email_magento_rma_guest_template',
                        'sales_email/magento_rma/guest_template',
                        'New RMA for Guest',
                    ],
                    [
                        'sales_email_magento_rma_auth_template',
                        'sales_email/magento_rma/auth_template',
                        'RMA Authorization',
                    ],
                    [
                        'sales_email_magento_rma_auth_guest_template',
                        'sales_email/magento_rma/auth_guest_template',
                        'RMA Authorization for Guest',
                    ],
                    [
                        'sales_email_magento_rma_comment_template',
                        'sales_email/magento_rma/comment_template',
                        'RMA Admin Comments',
                    ],
                    [
                        'sales_email_magento_rma_comment_guest_template',
                        'sales_email/magento_rma/comment_guest_template',
                        'RMA Admin Comments for Guest',
                    ],
                    [
                        'sales_email_magento_rma_customer_comment_template',
                        'sales_email/magento_rma/customer_comment_template',
                        'RMA Customer Comments',
                    ],
                    ['magento_scheduledimportexport_import_failed', '', 'Import Failed'],
                    ['magento_scheduledimportexport_export_failed', '', 'Export Failed'],
                    [
                        'system_magento_scheduled_import_export_log_error_email_template',
                        'system/magento_scheduled_import_export_log/error_email_template',
                        'File History Clean Failed',
                    ],
                ];
                $magentoEvents = $magentoEvents + $magentoEventsEnterprise;
            }

            foreach ($magentoEvents as $me) {
                if (!empty($me[1])) {
                    $select = $connection->select()
                        ->from($tableName, 'magento_event')
                        ->where('config_path = ?', $me[1]);

                    $magentoEvent = $connection->fetchOne($select);
                    if (!empty($magentoEvent)) {
                        $connection->update($tableName, ['template_id' => $me[0]], $connection->quoteInto('config_path = ?', $me[1]));
                        continue;
                    }
                }

                $select = $connection->select()
                    ->from($tableName, 'magento_event')
                    ->where('template_id = ?', $me[0]);

                $magentoEvent = $connection->fetchOne($select);

                if (empty($magentoEvent)) {
                    $connection->insert($tableName, [
                        'magento_event' => $me[2],
                        'config_path' => $me[1],
                        'template_id' => $me[0],
                    ]);
                }
            }
        }

        if (version_compare($context->getVersion(), "1.0.30", "<")) {
            $tableName = $setup->getTable('core_config_data');
            $setup->getConnection()->query(
                'UPDATE ' . $tableName
                . ' SET `path` = REPLACE(`path`, "emarsys_settings/", "emartech/")'
                . ' WHERE (INSTR(`path`, "emarsys_settings/"))'
            );
        }

        $setup->endSetup();
    }
}
