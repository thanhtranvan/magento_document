<?php
/**
 * data-install-3.5.1.php
 * PHP Version 5.5.9
 *
 * @category  Webinse
 * @package   Webinse_Ajaxcart
 * @author    Webinse Team <info@webinse.com>
 * @copyright 2016 Webinse Ltd. (https://www.webinse.com)
 * @license   The Open Software License 3.0
 * @link      http://opensource.org/licenses/OSL-3.0
 */

$installer = $this;
$installer->startSetup();
    $magentoVersion = Mage::getVersion();
    $value = version_compare($magentoVersion, '1.9', '<') ? '1' : '0';
    Mage::getConfig()->saveConfig('ajaxcart/info/include_old_jquery_version', $value, 'default', 0);
$installer->endSetup();
