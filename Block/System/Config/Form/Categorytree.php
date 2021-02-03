<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Block\System\Config\Form;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Model\ResourceModel\Customer;
use Magento\Framework\App\Request\Http;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\CategoryFactory;

class Categorytree extends Field
{
    /**
     * @var Http
     */
    protected $getRequest;

    /**
     * @var Customer
     */
    protected $customerResourceModel;

    /**
     * Categorytree constructor.
     *
     * @param Http $request
     * @param Customer $customerResourceModel
     * @param ScopeConfigInterface $configInterface
     * @param CategoryFactory $categoryFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Http $request,
        Customer $customerResourceModel,
        ScopeConfigInterface $configInterface,
        CategoryFactory $categoryFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->getRequest = $request;
        $this->customerResourceModel = $customerResourceModel;
        $this->configInterface = $configInterface;
        $this->categoryFactory = $categoryFactory;
        $this->_storeManager = $storeManager;
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $websiteId = $this->getRequest->getParam('website');
        $categoriesExcluded = $this->customerResourceModel->getDataFromCoreConfig(
            EmarsysHelper::XPATH_PREDICT_EXCLUDED_CATEGORIES,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
        $rootCategoryId = 1;
        list($catTree, $selectedCats) = $this->getTreeCategories($rootCategoryId);

        return '<div class="emarsys-search">'
            . '<div class="category-multi-select">'
            . '<div class="admin__field">'
            . '<div class="admin__field-control">'
            . '<div class="admin__action-multiselect-wrap action-select-wrap admin__action-multiselect-tree">'
            . '<div class="admin__action-multiselect" id="selectedCategories">' . $selectedCats . '</div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<input type="hidden" id="feed_export_categories" value="' . $categoriesExcluded . '"'
            . ' name="groups[feed_export][fields][categories][value]" />'
            . '<ul><li>'
            . '<div class="catg-sub-">'
            . '<input type="checkbox" disabled ="disabled" name="dummy-checkbox" />Root Category</div>'
            . $catTree
            . '</ul></li></div></div>'
            . "<script>
                function Unchecked(value) {
                    document.getElementById('catCheckBox_'+value).click();
                }
                function categoryClick(value,catName) {
                    var checkboxes = document.getElementsByName('checkbox');
                    var checkboxesChecked = [];
                    // loop over them all
                    for (var i=0; i<checkboxes.length; i++) {
                        // And stick the checked ones onto an array...
                        if (checkboxes[i].checked) {
                            checkboxesChecked.push(checkboxes[i].value);
                        }
                    }
                    document.getElementById('emarsys_predict_feed_export_excludedcategories').value= checkboxesChecked;
                    document.getElementById('feed_export_categories').value= checkboxesChecked;
                    if (document.getElementById('catCheckBox_'+value).checked == true) {
                        document.getElementById('selectedCategories').innerHTML += '<span id=\''+value+'\''
                          +' onclick=\'Unchecked('+value+')\' class=\"admin__action-multiselect-crumb\">'
                          +catName+'<button class=\"action-close\" type=\"button\">'
                          +'<span class=\"action-close-text\"></span></button></span>';
                    } else {
                        document.getElementById(value).remove();
                    }
                }
        </script>";
    }

    /**
     * @param  $parentId
     * @param int $level
     * @return array
     */
    public function getTreeCategories($parentId, $level = 1)
    {
        $websiteId = $this->getRequest->getParam('website');
        $categoriesExcluded = $this->configInterface->getValue(
            'emarsys_predict/feed_export/excludedcategories',
            'websites',
            $websiteId
        );
        $categoriesExcluded = explode(',', $categoriesExcluded);
        $allCategories = $this->categoryFactory->create()->getCollection()
            ->addAttributeToFilter('parent_id', ['eq' => $parentId]);
        $selectedCats = '';
        $html = '<ul class="category-' . $level . '">';
        foreach ($allCategories as $cat) {
            $checked = '';
            $category = $this->categoryFactory->create()->load($cat->getId());
            if (in_array($cat->getId(), $categoriesExcluded)) {
                $checked = ' checked=checked';
            }
            $level = $category->getLevel();
            $subcats = $category->getChildren();
            $disable = '';
            $name = 'checkbox';
            if ($level == 1) {
                $disable = " disabled=disabled";
                $name = 'dummy-checkbox';
            }
            $html .= '<li class="catg-sub-$categoryLevel' . $category->getLevel() . '">'
                . '<div class="catg-sub-' . $level . '">'
                . '<input type="checkbox"' . $checked . $disable . ' name="' . $name . '"'
                . ' onclick="categoryClick(this.value, \'' . $category->getName() . '\')"'
                . ' id="catCheckBox_' . $category->getId() . '" value="' . $category->getId() . '"/>'
                . $category->getName() . '</div>';

            if ($checked != '') {
                $selectedCats .= '<span id="' . $category->getId() . '"'
                    . ' onclick="Unchecked(' . $category->getId() . ')" class="admin__action-multiselect-crumb">'
                    . $category->getName()
                    . '<button class="action-close" type="button"><span class="action-close-text"></span></button>'
                    . '</span>';
            }
            if (count(array_filter(explode(",", $subcats))) > 0) {
                list($catTree, $selectedSubCats) = $this->getTreeCategories($category->getId(), $level);
                $html .= $catTree;
                $selectedCats .= $selectedSubCats;
            }
            $html .= '</li>';
        }
        $html .= '</ul>';

        return [$html, $selectedCats];
    }
}
