<?php
/**
 * Webinse_Ajaxcart_Block_System_Config_Extversion.php
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
 * Webinse_Ajaxcart_Block_System_Config_Extversion.php
 * Renderer for Extension version in the System configuration.
 *
 * @category  Webinse
 * @package   Webinse_Ajaxcart
 * @author    Webinse Team <info@webinse.com>
 * @copyright 2016 Webinse Ltd. (https://www.webinse.com)
 * @license   The Open Software License 3.0
 * @link      http://opensource.org/licenses/OSL-3.0
 */

class Webinse_Ajaxcart_Block_System_Config_Extversion extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $version = (string)Mage::getConfig()->getNode()->modules->Webinse_Ajaxcart->version;
        return "<b style='color:#12b52f'>".$version."</b>";
    }
}