<?php
/**
 * @category   Kiatng
 * @package    Kiatng_Shooter
 * @copyright  Copyright (c) 2024 Ng Kiat Siong
 * @license    GNU GPL v3.0
 */

abstract class Kiatng_Shooter_Controller_Abstract extends Mage_Core_Controller_Front_Action
{
    protected $_t2;

    /**
     * Only allow access for customer_id < 20
     *
     * @inheritdoc
     */
    public function preDispatch()
    {
        parent::preDispatch();
        $session = Mage::getSingleton('customer/session');
        if (!$session->authenticate($this)) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        } elseif ($session->getCustomerId() > 20) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            $this->norouteAction();
        }
        $this->_t2 = microtime(true);
        return;
    }

    /**
     * Display the output of a variable.
     *
     * @param mixed $var
     * @param string $title
     * @return void
     */
    protected function _echo($var, string $title = '')
    {
        $dt = microtime(true) - $this->_t2;
        Mage::helper('shooter')->echo($var, $title, $dt);
    }
}
