<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\FtpTestConnection;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\Ftp;
use Magento\Store\Model\ScopeInterface;

class Index extends Action
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Http
     */
    protected $getRequest;

    /**
     * @var Ftp
     */
    protected $ftp;

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param Http $request
     * @param Config $config
     * @param Ftp $ftp
     */
    public function __construct(
        Context $context,
        Http $request,
        Config $config,
        Ftp $ftp
    ) {
        $this->config = $config;
        $this->getRequest = $request;
        $this->ftp = $ftp;

        parent::__construct($context);
    }

    /**
     * Emarsys test connection api credentials
     *
     * @return bool|true
     * @throws LocalizedException
     */
    public function execute()
    {
        $result = false;
        $data = $this->getRequest->getParams();

        $hostname = $data['hostname'];
        $port = $data['port'];
        $username = $data['username'];
        $password = $data['password'];
        $ftpSsl = $data['ftpssl'];
        $passiveMode = $data['passivemode'];

        if (!$username || !$password || !$hostname || !$port) {
            return $result;
        }

        $result = $this->ftp->open(
            [
                'host' => $hostname,
                'port' => $port,
                'user' => $username,
                'password' => $password,
                'ssl' => $ftpSsl ? true : false,
                'passive' => $passiveMode ? true : false,
            ]
        );

        if ($result) {
            $scope = ScopeInterface::SCOPE_WEBSITES;
            $websiteId = $data['website'];
            $this->config->saveConfig(
                'emartech/ftp_settings/hostname',
                $data['hostname'],
                $scope,
                $websiteId
            );
            $this->config->saveConfig(
                'emartech/ftp_settings/port',
                $data['port'],
                $scope,
                $websiteId
            );
            $this->config->saveConfig(
                'emartech/ftp_settings/username',
                $data['username'],
                $scope,
                $websiteId
            );
            $this->config->saveConfig(
                'emartech/ftp_settings/ftp_password',
                $data['password'],
                $scope,
                $websiteId
            );
            $this->config->saveConfig(
                'emartech/ftp_settings/ftp_bulk_export_dir',
                $data['bulkexpdir'],
                $scope,
                $websiteId
            );
            $this->config->saveConfig(
                'emartech/ftp_settings/useftp_overssl',
                $data['ftpssl'],
                $scope,
                $websiteId
            );
            $this->config->saveConfig(
                'emartech/ftp_settings/usepassive_mode',
                $data['passivemode'],
                $scope,
                $websiteId
            );
            $this->messageManager->addSuccessMessage('Test connection successful !!!');
        }

        return $result;
    }
}
