<?php
/**
 * @category   Kiatng
 * @package    Kiatng_Shooter
 * @copyright  Copyright (c) 2025 Ng Kiat Siong
 * @license    GNU GPL v3.0
 */

class Kiatng_Shooter_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Return current local date and time
     *
     * @link http://www.php.net/manual/en/function.date.php
     */
    public function getLocalDateTime(string $format = 'd-m-Y H:i:s'): string
    {
        return Mage::getSingleton('core/date')->date($format);
    }

    /**
     * Convert seconds to human readable time duration
     */
    public function toHumanReadableTimeDuration(float $dt): string
    {
        if ($dt >= 86400) {
            $days = floor($dt / 86400);
            $hours = floor(($dt % 86400) / 3600);
            $minutes = floor(($dt % 3600) / 60);
            $seconds = $dt % 60;
            return $days . 'd ' . $hours . 'h ' . $minutes . 'm ' . number_format($seconds, 3) . 's';
        }
        if ($dt >= 3600) {
            $hours = floor($dt / 3600);
            $minutes = floor(($dt % 3600) / 60);
            $seconds = $dt % 60;
            return $hours . 'h ' . $minutes . 'm ' . number_format($seconds, 3) . 's';
        }
        if ($dt >= 60) {
            $minutes = floor($dt / 60);
            $seconds = $dt % 60;
            return $minutes . 'm ' . number_format($seconds, 3) . 's';
        }
        if ($dt >= 1) {
            return number_format($dt, 3) . 's';
        }
        if ($dt >= 0.001) {
            return number_format($dt * 1000, 3) . 'ms';
        }
        return number_format($dt * 1000000, 3) . 'μs';
    }

    /**
     * @return Mage_Api2_Model_Auth_User_Abstract|null
     */
    protected function _getRestAuthUser()
    {
        $getAuthUser = function() {
            /** @var Mage_Api2_Model_Server $this */
            return $this->_authUser;
        };
        return $getAuthUser->call(Mage::getSingleton('api2/server'));
    }

    /**
     * @see Aoe_Scheduler_Helper_Data of the same function
     * Return the current system user running this process
     */
    public function getRunningUser(): string
    {
        if (function_exists('posix_getpwuid')) {
            return posix_getpwuid(posix_geteuid())['name'];
        }

        return getenv('USERNAME')
            ?? getenv('USER')
            ?? function_exists('shell_exec') ? trim(shell_exec('whoami')) : get_current_user();
    }

    /**
     * @return string[] [username, user_role|'frontend'|'api'|'cron'|'rest'|'system']
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getSessionUser()
    {
        // Check cron first, as it may use admin session.
        $filename = isset($_SERVER['PHP_SELF']) ? basename($_SERVER['PHP_SELF']) : '';
        if ($filename === 'cron.php') {
            return [$this->getRunningUser(), 'cron'];
        }

        $session = Mage::getSingleton('admin/session');
        if ($session->isLoggedIn()) {
            $user = $session->getUser();
            return [$user->getUsername(), $user->getRole()->getRoleName()];
        }

        $session = Mage::getSingleton('customer/session');
        if ($session->isLoggedIn()) {
            $customer = $session->getCustomer();
            return [$customer->getEmail(), 'frontend'];
        }

        $session = Mage::getSingleton('api/session');
        if ($session->isLoggedIn()) {
            $user = Mage::getSingleton('api/session')->getUser();
            return [$user->getUsername(), 'api ' . Mage::getSingleton('api/server')->getApiName()];
        }

        if ($authUser = $this->_getRestAuthUser()) {
            return [$authUser->getLabel() . ' #' . $authUser->getUserId(), 'rest'];
        }

        return [$this->getRunningUser(), php_sapi_name() ?: 'system'];
    }

    /**
     * Write msg and optional var to log file with current store timestamp
     *
     * @param string $msg
     * @param mixed $var
     * @param string $filename
     * @return void
     */
    public function log(string $msg, $var = null, string $filename = 'shooter.log')
    {
        if ($fh = @fopen(Mage::getBaseDir('var') . DS . 'log' . DS . $filename, 'a+')) {
            list($username, $userrole) = $this->getSessionUser();
            $msg = "[{$this->getLocalDateTime()}][$username $userrole] $msg \r\n";
            if ($var !== null) {
                if ($var instanceof Varien_Object) {
                    $data = $var->getData();
                    foreach ($data as $k => $v) {
                        if ($v instanceof Varien_Object) {
                            $data[$k] = $v->getData();
                        }
                    }
                    $var = print_r($data, true);
                } elseif ($var instanceof Varien_Data_Collection) {
                    $var = print_r($var->toArray(), true);
                } else {
                    $var = print_r($var, true);
                }
                $msg .= $var . "\r\n";
            }
            fputs($fh, $msg, strlen($msg));
            fclose($fh);
        }
    }

    /**
     * Echo $var to browser
     *
     * @param mixed $var
     * @param string $title
     * @param float $dt Execution time in seconds
     * @return void
     */
    public function echo($var, string $title = '', float $dt = 0)
    {
        $humanReadableDt = $dt ? '(' . $this->toHumanReadableTimeDuration($dt) . ')' : '';
        if ($title || $humanReadableDt) {
            $title = "<h3>$title $humanReadableDt</h3>";
        }
        if ($var instanceof Varien_Object) {
            $output = $title.'<pre>'.print_r($var->getData(), true).'</pre>';
        } elseif ($var instanceof Varien_Data_Collection) {
            $output = $title.'<pre>'.print_r($var->toArray(), true).'</pre>';
        } else {
            $output = Zend_Debug::dump($var, $title, false);
        }

        Mage::app()->getResponse()->setHeader('Content-Type', 'text/html')->setBody($output);
    }

    /**
     * Echo request params and $_FILES
     *
     * @param mixed|null $var
     * @return void
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     * @throws Zend_Controller_Response_Exception
     */
    public function echoParams($var = null)
    {
        $output = '<h3>Params</h3>';
        $params  = Mage::app()->getRequest()->getParams();
        empty($_FILES) ?: $params['$_FILES'] = $_FILES;
        $output .=  Zend_Debug::dump($params, null, false);
        if ($var) {
            $output .= '<h3>Var</h3>';
            if ($var instanceof Varien_Object) {
                $output .= '<pre>'.print_r($var->getData(), true).'</pre>';
            } elseif ($var instanceof Varien_Data_Collection) {
                $output .= '<pre>'.print_r($var->toArray(), true).'</pre>';
            } else {
                $output .= Zend_Debug::dump($var, null, false);
            }
        }
        Mage::app()->getResponse()->setHeader('Content-Type', 'text/html')->setBody($output);
    }

    /**
     * Get trace stack
     *
     * @return void
     */
    public function trace(string $msg = '')
    {
        //$this->log('Trace Stack', Varien_Debug::backtrace(true, false));
        $this->log("Trace Stack: $msg", mageDebugBacktrace(true, false, true));
    }
}
