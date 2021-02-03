<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Console\Command;

use Emarsys\Emarsys\Helper\Data;
use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Emarsys\Emarsys\Model\Order;

/**
 * Command for deployment of Sample Data
 */
class EmarsysOrderExport extends Command
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var State
     */
    private $state;

    /**
     * @var Order
     */
    private $order;

    /**
     * EmarsysOrderExport constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param State $state
     * @param Order $order
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        State $state,
        Order $order
    ) {
        $this->storeManager = $storeManager;
        $this->state = $state;
        $this->order = $order;
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
                '--from="Y-m-d" [2017-12-31]'
            ),
            new InputOption(
                'to',
                null,
                InputOption::VALUE_OPTIONAL,
                '--to="Y-m-d" [2017-12-31]'
            ),
            new InputOption(
                'queue',
                null,
                InputOption::VALUE_OPTIONAL,
                '--queue="true"',
                false
            ),
        ];

        $this->setName('emarsys:export:order')
            ->setDescription('Order bulk export (--from=\'Y-m-d\' --to=\'Y-m-d\' --queue=\'true\')')
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
        $output->writeln('<info>Order customer bulk export.</info>');
        $output->writeln('');
        $output->writeln('<info>' . date('Y-m-d H:i:s') . '</info>');

        /** @var Store $store */
        foreach ($this->storeManager->getStores() as $storeId => $store) {
            if ($store->getConfig(Data::XPATH_EMARSYS_ENABLED) && $store->getConfig(Data::XPATH_SMARTINSIGHT_ENABLED)) {
                $fromDate = $input->getOption('from');
                $toDate = $input->getOption('to');
                $queue = $input->getOption('queue');
                try {
                    $this->order->syncOrders(
                        $storeId,
                        ($queue ? Data::ENTITY_EXPORT_MODE_AUTOMATIC : Data::ENTITY_EXPORT_MODE_MANUAL),
                        $fromDate,
                        $toDate
                    );
                } catch (Exception $e) {
                    $output->writeln($e->getMessage());
                }
            }
        }

        $output->writeln('<info>' . date('Y-m-d H:i:s') . '</info>');
        $output->writeln('');
        $output->writeln('<info>Order bulk export complete</info>');
        $output->writeln('');
    }
}
