<?php
/**
 * @category   Kiatng
 * @package    Kiatng_Shooter
 * @copyright  Copyright (c) 2025 Ng Kiat Siong
 * @license    GNU GPL v3.0
 */

class Kiatng_Shooter_LogController extends Kiatng_Shooter_Controller_Abstract
{
    /**
     * Display log files: error_get_last(), var/log/*.log, var/report/*.log
     */
    public function indexAction()
    {
        /** @var int number of lines from tail */
        $lines = $this->getRequest()->getParam('lines', 80);
        /** @var int number of seconds from the file modified time, -1 to get all errors */
        $secs = $this->getRequest()->getParam('secs', 80);

        $output = '<h2>Host: ' . gethostname() . ' GMT '. Mage::getSingleton('core/date')->gmtDate().'</h2>';

        // error_get_last() logged in index.php
        $flag = Mage::getModel('core/flag', ['flag_code' => 'error_get_last'])->loadSelf();
        if ($flag->getId()) {
            $dt = Mage::helper('core')->formatDate($flag->getLastUpdate(), Mage_Core_Model_Locale::FORMAT_TYPE_SHORT, true);
            if ($flag->hasFlagData()) {
                $url = Mage::getUrl('*/*/clearLastError');
                $output .= "<h3>error_get_last() at localtime <em>$dt</em> [<a href='$url'>Clear Last Error</a>]</h3>";
                $output .= '<pre>'.print_r($flag->getFlagData(), true).'</pre>';
            } else {
                $output .= "<h3>error_get_last()</h3>";
                $output .= "<p>Error was cleared at localtime <em>$dt</em></p>";
            }
        }

        $paths = [];
        // Get the error log file.
        if (($path = ini_get('error_log')) && file_exists($path)) {
            $paths[] = $path;
        }
        // Get all log files in var/log.
        $paths += glob(Mage::getBaseDir('log') . DS . "*.log");

        $helper = Mage::helper('shooter/file');
        foreach ($paths as $path) {
            $url = Mage::getUrl('*/*/tail', ['path' => base64_encode($path), 'lines' => 50]);
            if ($secs == -1 || time() - filemtime($path) <= $secs) {
                $output .= $helper->getTailHtml($path, $lines);
                $output .= "<p><a href='$url'>View More</a></p>";
            } else {
                $output .= "<h3><a href='$url'>$path</a></h3><p><em>No log in the last $secs seconds (param 'secs')</em></p>";
            }
        }

        // Get the latest error in var/report.
        $dir = Mage::getBaseDir('var') . DS . 'report';
        if ($latest = $helper->latest($dir)) {
            $output .= $helper->getTailHtml($latest, 100);
            $url = Mage::getUrl('*/*/deleteReport', ['fnm' => basename($latest)]);
            $output .= "<p><a href='$url'>Delete Report Permanently</a></p>";
        }

        $this->getResponse()->setHeader('Content-Type', 'text/html')->setBody($output);
    }

    /**
     * Delete the report file.
     */
    public function deleteReportAction()
    {
        $filename = $this->getRequest()->getParam('fnm');
        $path = Mage::getBaseDir('var') . DS . 'report' . DS . $filename;
        if (file_exists($path)) {
            unlink($path);
        }
        $this->_redirectReferer();
    }

    /**
     * Clear the last error flag.
     */
    public function clearLastErrorAction()
    {
        Mage::getModel('core/flag', ['flag_code' => 'error_get_last'])
            ->loadSelf()
            ->setData('flag_data', null)
            ->save();
        $this->_redirectReferer();
    }

    /**
     * Display the tail of files given params 'fnm' and 'dir'.
     * Accept file and dir names with wildcard, e.g. 'exception.log' or '*.log'.
     * If dir does not contains '/', it is relative to Mage::getBaseDir($dir).
     */
    public function tailAction()
    {
        $lines = $this->getRequest()->getParam('lines', 80);
        $path = $this->getRequest()->getParam('path');
        if ($path) {
            $paths = [base64_decode($path)];
        } else {
            $fnm = $this->getRequest()->getParam('fnm', 'exception.log');
            $dir = $this->getRequest()->getParam('dir', 'log');
            if (!str_contains($dir, '/')) {
                /** @see Mage_Core_Model_Config_Options for all the values of $dir */
                $dir = Mage::getBaseDir($dir);
            }
            $paths = glob($dir . DS . $fnm);
        }
        $output = '<h2>GMT '.Mage::getSingleton('core/date')->gmtDate().'</h2>';

        $helper = Mage::helper('shooter/file');
        foreach ($paths as $path) {
            $fnm = basename($path);
            $dt = Mage::getSingleton('core/date')->date('Y-m-d H:i:s', filemtime($path));
            $output .= "<h3>$fnm <em>$dt</em></h3>";
            if ($tail = $helper->tail($path, $lines)) {
                $tail = print_r($tail, true);
                $output .= "<pre>$tail</pre>";
            } else {
                $output .= "<p><em>empty</em></p>";
            }
        }

        $this->getResponse()->setHeader('Content-Type', 'text/html')->setBody($output);
    }
}
