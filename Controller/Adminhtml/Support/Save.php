<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Support;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface as Logger;
use Zend_Filter_Input;

class Save extends Action
{
    /**
     * @var Session
     */
    protected $authSession;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var
     */
    protected $data;

    /**
     * @var
     */
    protected $email;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var \Magento\Backend\Model\Session
     */
    protected $session;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var StateInterface
     */
    protected $inlineTranslation;

    /**
     * Save constructor.
     *
     * @param Session $authSession
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param StoreManagerInterface $storeManager
     * @param EmarsysHelper $emarsysHelper
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param Context $context
     * @param Logger $logger
     */
    public function __construct(
        Session $authSession,
        ScopeConfigInterface $scopeConfigInterface,
        StoreManagerInterface $storeManager,
        EmarsysHelper $emarsysHelper,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        Context $context,
        Logger $logger
    ) {
        parent::__construct($context);
        $this->authSession = $authSession;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->storeManager = $storeManager;
        $this->emarsysHelper = $emarsysHelper;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->logger = $logger;
        $this->session = $context->getSession();
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $req = $this->emarsysHelper->getRequirementsInfo();
        if ($this->getRequest()->getParams()) {
            $data = $this->getRequest()->getParams();
            try {
                $typeArray = explode('||', $data['type']);
                $type = trim($typeArray[0]);    // $typeArray[0] is the type
                $name = trim($data['firstname']);
                $email = trim($typeArray[1]);   // $typeArray[1] are email recievers
                $subject = trim($data['subject']);
                $priority = trim($data['priority']);
                $message = trim($data['message']);
                // it depends on the template variables
                $templateVars = [];
                $templateVars['type'] = $type;
                $templateVars['name'] = $name;
                $templateVars['email'] = $email;
                $templateVars['subject'] = $type . ' - ' . $subject;
                $templateVars['priority'] = $priority;
                $templateVars['message'] = $message;
                $templateVars['store_name'] = $this->storeManager->getStore()->getName();
                $templateVars['domain'] = $this->storeManager->getStore()->getBaseUrl();
                $templateVars['phpvalue'] = $req['php_version']['current']['value'];
                $templateVars['memoryvalue'] = $req['memory_limit']['current']['value'];
                $templateVars['magentovalue'] = $req['magento_version']['current']['value'];
                $templateVars['curlvalue'] = $req['curl_enabled']['current']['value'];
                $templateVars['soapvalue'] = $req['soap_enabled']['current']['value'];
                $inputFilter = new Zend_Filter_Input(
                    [],
                    [],
                    $data
                );
                $user = $this->authSession->getUser();
                $from = [
                    'email' => $user->getEmail(),
                    'name' => $user->getUsername(),
                ];
                $storeId = $this->emarsysHelper->getFirstStoreId();
                $emailRecievers = explode(',', $typeArray[1]);

                foreach ($emailRecievers as $emailReciever) {
                    $to = [
                        'email' => $emailReciever,
                        'name' => $name,
                    ];
                    $templateOptions = [
                        'area' => FrontNameResolver::AREA_CODE,
                        'store' => $storeId,
                    ];

                    $this->inlineTranslation->suspend();
                    $transport = $this->transportBuilder
                        ->setTemplateIdentifier('help_email_template_id')
                        ->setTemplateOptions($templateOptions)
                        ->setTemplateVars($templateVars)
                        ->setFrom($from)
                        ->addTo($to)
                        ->getTransport();
                    $transport->sendMessage();
                    $this->inlineTranslation->resume();
                }
                $data = $inputFilter->getUnescaped();
                $id = $this->getRequest()->getParam('id');
                if ($id) {
                    throw new LocalizedException(__('The wrong item is specified.'));
                }
                $this->messageManager->addSuccessMessage(__('Request send succesfully'));
                $this->_redirect('emarsys_emarsys/support/index');
                return;
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage(__($e->getMessage()));
                $this->logger->critical($e);
                $this->session->setPageData($data);
                $this->_redirect('emarsys_emarsys/support/index');
                return;
            }
        }
        $this->_redirect('emarsys_emarsys/support/index');
    }
}
