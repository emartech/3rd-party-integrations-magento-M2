<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model;

class Config extends \Magento\Framework\DataObject
{
    /**
     * constructor
     */
    public function _construct()
    {
        parent::_construct();
        //$this->_init('Emarsys\Emarsys\Model\ResourceModel\Emarsysmagentoevents');
    }


    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Config\ValueInterface $backendModel,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Framework\App\Config\ValueFactory $configValueFactory,
        $data = []
    ) {
        parent::__construct($data = []);
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
        $this->_backendModel = $backendModel;
        $this->_transaction = $transaction;
        $this->_configValueFactory = $configValueFactory;
        $this->_storeId=(int)$this->_storeManager->getStore()->getId();
        $this->_storeCode=$this->_storeManager->getStore()->getCode();
    }
    /**
     * @param $path
     * @return mixed
     * Function for getting Config value of current store
     */
    public function getCurrentStoreConfigValue($path)
    {
        return $this->_scopeConfig->getValue($path, 'store', $this->_storeCode);
    }

    /**
     * Function for setting Config value of current store
     * @param string $path,
     * @param string $value,
     */
    public function setCurrentStoreConfigValue($path, $value)
    {
        $data = [
            'path' => $path,
            'scope' =>  'stores',
            'scope_id' => $this->_storeId,
            'scope_code' => $this->_storeCode,
            'value' => $value,
        ];

        $this->_backendModel->addData($data);
        $this->_transaction->addObject($this->_backendModel);
        $this->_transaction->save();
    }

}
