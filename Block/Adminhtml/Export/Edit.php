<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Export;

use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Magento\Framework\App\Request\Http;

class Edit extends Container
{
    protected $_objectId = 'entity_id';
    protected $_blockGroup = 'Emarsys_Emarsys';

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var Http
     */
    protected $getRequest;

    /**
     * Edit constructor.
     *
     * @param Context $context
     * @param Http $request
     * @param EmarsysHelper $emarsysHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Http $request,
        EmarsysHelper $emarsysHelper,
        array $data = []
    ) {
        $this->getRequest = $request;
        $this->emarsysHelper = $emarsysHelper;
        parent::__construct($context, $data);
    }

    /**
     * Check permission for passed action
     *
     * @param string $resourceId
     * @return bool
     */
    protected function _isAllowedAction($resourceId)
    {
        return $this->_authorization->isAllowed($resourceId);
    }
}
