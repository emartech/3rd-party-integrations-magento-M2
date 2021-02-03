<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Email;

use Magento\Backend\App\Action\Context;
use Magento\Email\Controller\Adminhtml\Email\Template as EmailTemplate;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Email\Model\BackendTemplateFactory;

class Template extends EmailTemplate
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Email::template';

    /**
     * Core registry
     *
     * @var Registry
     */
    protected $_coreRegistry = null;

    /**
     * @var BackendTemplate
     */
    protected $backendTemplate;

    /**
     * Template constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param BackendTemplateFactory $backendTemplate
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        BackendTemplateFactory $backendTemplate
    ) {
        $this->_coreRegistry = $coreRegistry;
        $this->backendTemplate = $backendTemplate;
        parent::__construct($context, $coreRegistry);
    }

    /**
     * Dispatch request
     *
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        $data = $this->request->getParams();
    }

    /**
     * Load email template from request
     *
     * @param string $idFieldName
     * @return mixed
     */
    protected function _initTemplate($idFieldName = 'template_id')
    {
        $id = (int)$this->getRequest()->getParam($idFieldName);
        $model = $this->backendTemplate->create();
        if ($id) {
            $model->load($id);
        }
        if (!$this->_coreRegistry->registry('email_template')) {
            $this->_coreRegistry->register('email_template', $model);
        }
        if (!$this->_coreRegistry->registry('current_email_template')) {
            $this->_coreRegistry->register('current_email_template', $model);
        }
        return $model;
    }

    public function initTemplate($idFieldName = 'template_id')
    {
        return $this->_initTemplate($idFieldName);
    }
}
