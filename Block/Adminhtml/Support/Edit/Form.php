<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Support\Edit;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Locale\ListsInterface;
use Magento\Framework\Registry;
use Magento\User\Model\UserFactory;

class Form extends Generic
{
    /**
     * @var Session
     */
    protected $_authSession;

    /**
     * @var UserFactory
     */
    protected $_userFactory;

    /**
     * @var ListsInterface
     */
    protected $_localeLists;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param UserFactory $userFactory
     * @param Session $authSession
     * @param ListsInterface $localeLists
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        UserFactory $userFactory,
        Session $authSession,
        ListsInterface $localeLists,
        array $data = []
    ) {
        $this->_userFactory = $userFactory;
        $this->_authSession = $authSession;
        $this->_localeLists = $localeLists;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * {@inheritdoc}
     */
    protected function _prepareForm()
    {
        $userId = $this->_authSession->getUser()->getId();
        $user = $this->_userFactory->create()->load($userId);
        $user->unsetData('password');
        $url = $this->getUrl("*/*/supportEmails");
        /**
         * @var \Magento\Framework\Data\Form $form
         */
        $form = $this->_formFactory->create();

        $fieldset = $form->addFieldset("support_form", ["legend" => "Support Information"]);
        $fieldset->addField(
            "type",
            "select",
            [
                'label' => 'Support Type',
                'name' => 'type',
                'required' => true,
                'values' => [
                    ['value' => '', 'label' => 'Please Select'],
                    ['value' => 'Sales Support||connect@emarsys.com', 'label' => 'Sales Support'],
                    ['value' => 'Customization Service||support@emarsys.com', 'label' => 'Customization Service'],
                    [
                        'value' => 'Feedback and Complaint||connect@emarsys.com,support@emarsys.com',
                        'label' => 'Feedback and Complaint',
                    ],
                    ['value' => 'Technical Support||support@emarsys.com', 'label' => 'Technical Support'],
                    ['value' => 'Urgent Issue||support@emarsys.com', 'label' => 'Urgent Issue'],
                    ['value' => 'Installation Service||support@emarsys.com', 'label' => 'Installation Service'],
                    ['value' => 'Request Upgrade||support@emarsys.com', 'label' => 'Request Upgrade'],
                    ['value' => 'Other||connect@emarsys.com,support@emarsys.com', 'label' => 'Other'],
                ],
            ]
        );

        $fieldset->addField(
            "firstname",
            "text",
            [
                "label" => "Name",
                "class" => "required-entry",
                "required" => true,
                "name" => "firstname",
            ]
        );

        $fieldset->addField(
            "subject",
            "text",
            [
                "label" => "Subject",
                "class" => "required-entry",
                "required" => true,
                "name" => "subject",
            ]
        );

        $fieldset->addField(
            "priority",
            "select",
            [
                'label' => 'Priority',
                'name' => 'priority',
                'values' => [
                    [
                        'value' => 'Normal',
                        'label' => 'Normal',
                    ],
                    [
                        'value' => 'Medium',
                        'label' => 'Medium',
                    ],

                    [
                        'value' => 'High',
                        'label' => 'High',
                    ],
                    [
                        'value' => 'Urgent',
                        'label' => 'Urgent',
                    ],

                ],
            ]
        );

        $fieldset->addField(
            'message',
            'textarea',
            [
                'name' => 'message',
                'label' => 'Message',
                'title' => 'Message',
                'required' => true,
            ]
        );

        $fieldset->addField(
            "email",
            "text",
            [
                "label" => "Email",
                "name" => "email",
                "id" => "email",
                "index" => "email",
                "class" => "hiddensupportemail",
            ]
        );

        $data = $user->getData();
        $form->setValues($data);
        $form->setAction($this->getUrl('*/*/save'));
        $form->setMethod('post');
        $form->setUseContainer(true);
        $form->setId('edit_form');

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
