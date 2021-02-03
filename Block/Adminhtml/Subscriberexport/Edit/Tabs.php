<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\Adminhtml\Subscriberexport\Edit;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget;
use Magento\Backend\Model\Auth\Session;
use Magento\Cms\Model\Wysiwyg\ConfigFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Json\EncoderInterface;
use Magento\Framework\View\Element\AbstractBlock;

class Tabs extends \Magento\Backend\Block\Widget\Tabs
{
    /**
     * @var ConfigFactory
     */
    protected $wysiwygConfigFactory;

    /**
     * Tabs constructor.
     *
     * @param Context $context
     * @param EncoderInterface $jsonEncoder
     * @param Session $authSession
     * @param ConfigFactory $wysiwygConfigFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        EncoderInterface $jsonEncoder,
        Session $authSession,
        ConfigFactory $wysiwygConfigFactory,
        array $data = []
    ) {
        $this->wysiwygConfigFactory = $wysiwygConfigFactory;
        parent::__construct($context, $jsonEncoder, $authSession, $data);
    }

    /**
     * Constructor
     */
    public function _construct()
    {
        parent::_construct();
        $this->setId("subscriberexport_tabs");
        $this->setDestElementId("edit_form");
    }

    /**
     * @return Widget|AbstractBlock
     * @throws LocalizedException
     */
    protected function _beforeToHtml()
    {
        $this->addTab(
            'form_section',
            [
                'content' => $this->getLayout()
                    ->createBlock(\Emarsys\Emarsys\Block\Adminhtml\Subscriberexport\Edit\Tab\Form::class)->toHtml(),
                'active' => true,
            ]
        );
        return parent::_beforeToHtml();
    }

    /**
     * @return Mage_Core_Block_Abstract|void
     * @throws LocalizedException
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $wysiwyg = $this->wysiwygConfigFactory->create();
        if ($wysiwyg->isEnabled()) {
            $this->getLayout()->getBlock('head')->setCanLoadTinyMce(true);
        }
    }
}
