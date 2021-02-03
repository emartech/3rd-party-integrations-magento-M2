<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Console\Command;

use Emarsys\Emarsys\Helper\Cron;
use Emarsys\Emarsys\Helper\Data;
use Emarsys\Emarsys\Model\ResourceModel\Customer;
use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Emarsys\Emarsys\Model\Api\Contact;

/**
 * Command for deployment of Sample Data
 */
class EmarsysCustomerExport extends Command
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Customer
     */
    protected $customerResourceModel;

    /**
     * @var State
     */
    protected $state;

    /**
     * @var Contact
     */
    private $contact;

    /**
     * EmarsysCustomerExport constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param Customer $customerResourceModel
     * @param State $state
     * @param Contact $contact
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Customer $customerResourceModel,
        State $state,
        Contact $contact
    ) {
        $this->storeManager = $storeManager;
        $this->customerResourceModel = $customerResourceModel;
        $this->state = $state;
        $this->contact = $contact;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $options = [
            new InputOption(
                'from',
                null,
                InputOption::VALUE_OPTIONAL,
                '--from="Y-m-d" (2017-12-31)'
            ),
            new InputOption(
                'to',
                null,
                InputOption::VALUE_OPTIONAL,
                '--to="Y-m-d" (2017-12-31)'
            ),
            new InputOption(
                'page',
                null,
                InputOption::VALUE_OPTIONAL,
                '--page="1"'
            ),
        ];

        $this->setName('emarsys:export:customer')
            ->setDescription(
                'Customer bulk export'
                . ' (--from=\'Y-m-d\' (2016-01-31)'
                . ' --to=\'Y-m-d\' (2017-12-31)'
                . ' --page=\'1\')'
            )
            ->setDefinition($options);
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(Area::AREA_GLOBAL);
        $output->writeln('');
        $output->writeln('<info>Starting customer bulk export.</info>');
        $output->writeln('');
        $output->writeln('<info>' . date('Y-m-d H:i:s') . '</info>');

        /** @var Store $store */
        foreach ($this->storeManager->getStores() as $storeId => $store) {
            if ($store->getConfig(Data::XPATH_EMARSYS_ENABLED)
                && $store->getConfig(Data::XPATH_EMARSYS_ENABLED)
            ) {
                $data = [];
                $data['page'] = ($input->getOption('page') && !empty($input->getOption('page')))
                    ? $input->getOption('page')
                    : 1;
                $data['fromDate'] = ($input->getOption('from') && !empty($input->getOption('from')))
                    ? $input->getOption('from') . ' 00:00:01'
                    : '';
                $data['toDate'] = ($input->getOption('to') && !empty($input->getOption('to')))
                    ? $input->getOption('to') . ' 23:59:59'
                    : '';
                $data['website'] = $store->getWebsiteId();
                $data['storeId'] = $storeId;
                try {
                    $this->contact->syncFullContactUsingApi(
                        Cron::CRON_JOB_CUSTOMER_BULK_EXPORT_API,
                        $data
                    );
                    $output->writeln('Processed <info>store: ' . $storeId . '</info>');
                } catch (Exception $e) {
                    $output->writeln($e->getMessage());
                    $output->writeln($e->getTrace());
                }
            }
        }

        $output->writeln('<info>' . date('Y-m-d H:i:s') . '</info>');
        $output->writeln('');
        $output->writeln('<info>Customer bulk export complete</info>');
        $output->writeln('');
    }
}
