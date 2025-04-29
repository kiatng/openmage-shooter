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
     * Validate allowed customer_id.
     *
     * @inheritdoc
     */
    public function preDispatch()
    {
        parent::preDispatch();
        $session = Mage::getSingleton('customer/session');
        if (!$session->authenticate($this)) {
            $session->unsIsAllowShooter();
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        } elseif (!$this->_isAllowed()) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            $this->norouteAction();
        }
        $this->_t2 = microtime(true);
        return;
    }

    /**
     * Check if the login user is allowed access.
     *
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        $session = Mage::getSingleton('customer/session');
        if (is_bool($session->getIsAllowShooter())) {
            return $session->getIsAllowShooter();
        }

        $allow = false;
        $id = (int) $session->getId();
        if ($id <= (int) Mage::getConfig()->getNode('shooter/access/up_to_ids')) {
            $allow = true;
        }

        $ids = (string) Mage::getConfig()->getNode('shooter/access/other_ids');
        if ($ids && !$allow) {
            $ids = explode(',', $ids);
            if (in_array($id, $ids)) {
                $allow = true;
            }
        }

        $session->setIsAllowShooter($allow);
        return $allow;
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
