<?php
/**
 * Webinse_Ajaxcart_Block_System_Config_Mageversion.php
 * PHP Version 5.5.9
 *
 * @category  Webinse
 * @package   Webinse_Ajaxcart
 * @author    Webinse Team <info@webinse.com>
 * @copyright 2016 Webinse Ltd. (https://www.webinse.com)
 * @license   The Open Software License 3.0
 * @link      http://opensource.org/licenses/OSL-3.0
 */

/**
 * Webinse_Ajaxcart_Block_System_Config_Mageversion.php
 * Renderer for Magento version in the System configuration.
 *
 * @category  Webinse
 * @package   Webinse_Ajaxcart
 * @author    Webinse Team <info@webinse.com>
 * @copyright 2016 Webinse Ltd. (https://www.webinse.com)
 * @license   The Open Software License 3.0
 * @link      http://opensource.org/licenses/OSL-3.0
 */

class Webinse_Ajaxcart_Block_System_Config_Mageversion extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return "<b style='color:#ff7932'>" .Mage::getVersion()."</b>";
    }
}