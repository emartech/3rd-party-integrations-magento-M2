<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Console\Command;

use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Emarsys\Emarsys\Model\ResourceModel\Async\CollectionFactory;
use Emarsys\Emarsys\Model\Api\Api as EmarsysModelApiApi;

/**
 * Command for deployment of Sample Data
 */
class EmarsysAsyncExport extends Command
{
    /**
     * @var State
     */
    private $state;

    /**
     * @var CollectionFactory
     */
    private $asyncCollection;

    /**
     * @var EmarsysModelApiApi
     */
    private $api;

    /**
     * EmarsysAsyncExport constructor.
     *
     * @param State $state
     * @param CollectionFactory $asyncCollection
     * @param EmarsysModelApiApi $api
     */
    public function __construct(
        State $state,
        CollectionFactory $asyncCollection,
        EmarsysModelApiApi $api
    ) {
        $this->state = $state;
        $this->asyncCollection = $asyncCollection;
        $this->api = $api;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('emarsys:export:async')
            ->setDescription('Async export');
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(Area::AREA_GLOBAL);
        $output->writeln('');
        $output->writeln('<info>Starting Async export.</info>');
        $output->writeln('');
        $output->writeln('<info>' . date('Y-m-d H:i:s') . '</info>');

        $collection = $this->asyncCollection->create();

        /** @var \Emarsys\Emarsys\Model\Async $item */
        foreach ($collection as $item) {
            try {
                $this->api->setWebsiteId($item->getWebsiteId());

                if ($item->getEndpoint() == EmarsysModelApiApi::CONTACT_CREATE_IF_NOT_EXISTS) {
                    $response = $this->api->createContactInEmarsys(\Zend_Json::decode($item->getRequestBody()));
                } else {
                    list($buildRequest, $requestBody) = \Zend_Json::decode($item->getRequestBody());
                    $response = $this->api->createContactInEmarsys($buildRequest);
                    if (isset($response['status']) && ($response['status'] == 200)
                        || (
                            $response['status'] == 400 && isset($response['body']['replyCode'])
                            && $response['body']['replyCode'] == 2009
                        )
                    ) {
                        //contact synced to emarsys successfully
                        $response = $this->api->sendRequest('POST', $item->getEndpoint(), $requestBody);
                    }
                }
                if (isset($response['status']) && ($response['status'] = 200)) {
                    $item->delete();
                }
                $output->writeln(
                    'status => ' . $response['status']
                    . ' |~| Email => ' . $item->getEmail()
                    . ' |~| replyCode => ' . $response['body']['replyCode']
                    . ' |~| replyText => ' . $response['body']['replyText']
                );
            } catch (Exception $e) {
                $output->writeln(
                    'status =>  <info>EXCEPTION</info>'
                    . ' |~| Email => ' . $item->getEmail()
                    . ' |~| error_message => ' . $e->getMessage()
                );
            }
        }

        $output->writeln('<info>' . date('Y-m-d H:i:s') . '</info>');
        $output->writeln('');
        $output->writeln('<info>Product bulk export complete</info>');
        $output->writeln('');
    }
}
