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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Emarsys\Emarsys\Model\Product;
use Emarsys\Emarsys\Model\ProductExportAsync as ProductExportAsync;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Command for deployment of Sample Data
 */
class EmarsysProductExport extends Command
{
    /**
     * @var State
     */
    private $state;

    /**
     * @var Product
     */
    private $product;

    /**
     * @var ProductExportAsync
     */
    private $productAsync;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * EmarsysProductExport constructor.
     *
     * @param State $state
     * @param Product $product
     * @param ProductExportAsync $productAsync
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        State $state,
        Product $product,
        ProductExportAsync $productAsync,
        StoreManagerInterface $storeManager
    ) {
        $this->state = $state;
        $this->product = $product;
        $this->productAsync = $productAsync;
        $this->storeManager = $storeManager;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('emarsys:export:product')
            ->setDescription('Product bulk export');
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(Area::AREA_GLOBAL);
        $output->writeln('');
        $output->writeln('<info>Starting product bulk export.</info>');
        $output->writeln('');
        $output->writeln('<info>' . date('Y-m-d H:i:s') . '</info>');

        try {
            $async = $this->storeManager->getStore()->getConfig('emarsys_predict/enable/async');
            if (!$async) {
                $output->writeln('Regular');
                $this->product->consolidatedCatalogExport(Data::ENTITY_EXPORT_MODE_MANUAL);
            } else {
                $output->writeln('Async');
                $this->productAsync->run(Data::ENTITY_EXPORT_MODE_MANUAL);
            }
        } catch (Exception $e) {
            $output->writeln($e->getMessage());
        }

        $output->writeln('<info>' . date('Y-m-d H:i:s') . '</info>');
        $output->writeln('');
        $output->writeln('<info>Product bulk export complete</info>');
        $output->writeln('');
    }
}
