<?php
/**
 * @category   Kiatng
 * @package    Kiatng_Shooter
 * @copyright  Copyright (c) 2024 Ng Kiat Siong
 * @license    GNU GPL v3.0
 */

class Kiatng_Shooter_InfoController extends Kiatng_Shooter_Controller_Abstract
{
    /**
     * Display PHP info.
     */
    public function indexAction()
    {
        $this->getResponse()->setHeader('Content-Type', 'text/html')->setBody(phpinfo());
    }

    /**
     * Display OpenMage version info.
     */
    public function verAction()
    {
        $this->_echo(
            Mage::getOpenMageVersionInfo(),
            'Version ' . Mage::getOpenMageVersion() . ' (' . Mage::getVersion() .')'
        );
    }

    /**
     * Display server info: host name, public IP, PHP version, OS, etc.
     */
    public function serverAction()
    {
        $vars = [
            'host_name' => gethostname(),
            'public_ip' => file_get_contents('https://api.ipify.org'),
            'php_version' => PHP_VERSION,
            'php_uname'
        ];
        if (strtolower(substr(PHP_OS, 0, 5)) === 'linux') {
            $files = glob('/etc/*-release');
            foreach ($files as $file) {
                $lines = array_filter(array_map(function($line) {
                    // split value from key
                    $parts = explode('=', $line);

                    // makes sure that "useless" lines are ignored (together with array_filter)
                    if (count($parts) !== 2) return false;

                    // remove quotes, if the value is quoted
                    $parts[1] = str_replace(['"', "'"], '', $parts[1]);
                    return $parts;

                }, file($file)));

                foreach ($lines as $line) {
                    $vars['OS'][$line[0]] = trim($line[1]);
                }
            }
        }
        $vars['$_SERVER'] = $_SERVER;
        $vars['client'] = Mage::helper('core/http')->getRemoteAddr();
        $vars[Mage_Core_Helper_Data::XML_PATH_DEV_ALLOW_IPS] = Mage::getStoreConfig(Mage_Core_Helper_Data::XML_PATH_DEV_ALLOW_IPS, Mage::app()->getStore());
        $this->_echo($vars, 'Server Info');
    }

    /**
     * Display design info: configured package, actual package, theme info, etc.
     */
    public function designInfoAction()
    {
        $package = Mage::getSingleton('core/design_package');
        $configuredPackage = Mage::getStoreConfig('design/package/name', $package->getStore());
        $actualPackage = $package->getPackageName();
        $data = [
            'store' => $package->getStore()->getData(),
            'configured_packaged' => $configuredPackage,
            'actual_package' => $actualPackage,
            'theme' => [
                'frontend' => $package->getTheme('frontend'),
                'adminhtml' => $package->getTheme('adminhtml'),
                'locale' => $package->getTheme('locale'),
                'layout' => $package->getTheme('layout'),
                'template' => $package->getTheme('template'),
                'default' => $package->getTheme('default'),
                'skin' => $package->getTheme('skin')
            ],
            'area' => $package->getArea(),
            'fallback_theme' => $package->getFallbackTheme(),
            'package_list' => $package->getPackageList(),
            'theme_list' => $package->getThemeList()
        ];

        if ($actualPackage != $configuredPackage) {
            $data['dbug_info']['is_designPackageExists'] = $package->designPackageExists($configuredPackage) ? "$configuredPackage exists" : "$configuredPackage missing";
            $data['dbug_info'][$configuredPackage.'_dir'] = Mage::getBaseDir('design') . DS . 'frontend' . DS . $configuredPackage;
            $data['dbug_info']['user-agents'] =  Mage::getStoreConfig('design/package/ua_regexp', $package->getStore());
            $data['dbug_info']['packagename_after_reset'] = $package->setPackageName()->getPackageName();
            /** @var Mage_Core_Model_Design $design */
            $design = Mage::getModel('core/design');
            $data['dbug_info']['design_change'] = $design->loadChange($package->getStore()->getId())->getData();
            $data['dbug_info']['design_change_note'] = 'Check backend > System > Design for entries';
        }

        $this->_echo($data, 'Design Info');
    }

    /**
     * Display store config given param 'path'.
     * Refresh cache if param 'refresh' is set.
     */
    public function storeConfigAction()
    {
        $path = $this->getRequest()->getParam('path');
        if ($this->getRequest()->getParam('refresh')) {
            Mage::getConfig()->cleanCache();
        }
        $result = Mage::getStoreConfig($path);
        if ($result === null) {
            $result = ($node = Mage::getConfig()->getNode($path))
                ? $node->asArray()
                : "Invalid path $path (add param 'refresh' to refresh the cache.)";
        }

        $this->_echo($result);
    }

    /**
     * Display database info: version, host, name, etc.
     */
    public function dbAction()
    {
        $conn = Mage::getModel('core/resource')->getConnection('core_read');
        $this->_echo(
            [
                'version' => $conn->fetchOne('SELECT VERSION()'),
                'host' => $conn->fetchOne('SELECT DATABASE()'),
                'table_prefix' => (string)Mage::app()->getConfig()->getTablePrefix(),
            ],
            'Database Info'
        );
    }
}
