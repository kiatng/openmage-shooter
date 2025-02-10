<?php
/**
 * @category   Kiatng
 * @package    Kiatng_Shooter
 * @copyright  Copyright (c) 2025 Ng Kiat Siong
 * @license    GNU GPL v3.0
 */

class Kiatng_Shooter_Oauth2Controller extends Kiatng_Shooter_Controller_Abstract
{
    const STATE_INIT = 0;
    const STATE_ACCESS_TOKEN = 1;
    const STATE_RESOURCE = 2;

    // OAuth2 endpoints
    const TOKEN_ENDPOINT = '/oauth/token';
    const AUTH_ENDPOINT = '/oauth/authorize';

    public function preDispatch()
    {
        // Handle session restoration similar to REST controller
        if (
            $this->getRequest()->getActionName() === 'callback'
            && !Mage::getSingleton('customer/session')->isLoggedIn()
            && $sid = $this->getRequest()->getParam('ssid')
        ) {
            Mage::getSingleton('core/cookie')->set(self::SESSION_NAMESPACE, $sid);
            $url = Mage::getUrl('*/*', ['_current' => true, '_use_rewrite' => true, '_query' => $_GET]);
            echo "<script>window.location = '$url';</script>";
            die();
        }

        parent::preDispatch();
    }

    /**
     * Entry point to OAuth2 flow
     */
    public function indexAction()
    {
        $session = Mage::getSingleton('customer/session');
        $state = $session->getOauth2State() ?: self::STATE_INIT;

        switch ($state) {
            case self::STATE_INIT:
                $this->_init();
                break;
            case self::STATE_ACCESS_TOKEN:
                $this->callbackAction();
                break;
            case self::STATE_RESOURCE:
                $this->_renderLayout($this->__('OAuth2 REST Resources'), 'resource');
                break;
        }
    }

    /**
     * Initialize OAuth2 flow
     */
    protected function _init()
    {
        $session = Mage::getSingleton('customer/session');
        // Clear previous OAuth2 data
        foreach ($session->getData() as $k => $v) {
            if (strpos($k, 'oauth2') === 0) {
                $session->unsetData($k);
            }
        }
        $session->setOauth2State(self::STATE_INIT);
        $this->_renderLayout($this->__('Test OAuth 2.0'), 'oauth');
    }

    /**
     * Process OAuth2 form submission
     */
    public function oauth2PostAction()
    {
        $host = rtrim($this->getRequest()->getPost('url', ''), '/');
        if (!$host) {
            return $this->_redirect('*/*/new');
        }

        $session = Mage::getSingleton('customer/session');
        $session->setOauth2Url($host);
        $session->setOauth2ClientId($this->getRequest()->getPost('client_id'));
        $session->setOauth2ClientSecret($this->getRequest()->getPost('client_secret'));
        $session->setOauth2UserType($this->getRequest()->getPost('user_type'));
        $session->setOauth2State(self::STATE_ACCESS_TOKEN);

        // Generate state parameter for CSRF protection
        $state = bin2hex(random_bytes(16));
        $session->setOauth2StateParam($state);

        // Build authorization URL
        $params = [
            'response_type' => 'code',
            'client_id' => $session->getOauth2ClientId(),
            'redirect_uri' => Mage::getUrl('*/*/callback', ['ssid' => $session->getEncryptedSessionId()]),
            'state' => $state,
            'scope' => 'all' // Adjust scope as needed
        ];

        $authUrl = $host .
            ($session->getOauth2UserType() === 'admin' ? '/admin' : '') .
            self::AUTH_ENDPOINT . '?' . http_build_query($params);

        $this->_redirectUrl($authUrl);
    }

    /**
     * Handle OAuth2 callback
     */
    public function callbackAction()
    {
        $session = Mage::getSingleton('customer/session');
        $code = $this->getRequest()->getParam('code');
        $state = $this->getRequest()->getParam('state');

        // Verify state parameter
        if ($state !== $session->getOauth2StateParam()) {
            $session->addError($this->__('Invalid state parameter. Possible CSRF attack.'));
            return $this->_init();
        }

        if (!$session->getOauth2AccessToken()) {
            try {
                $accessToken = $this->_getAccessToken($code);
                $session->setOauth2AccessToken($accessToken);
                $session->setOauth2State(self::STATE_RESOURCE);
            } catch (Exception $e) {
                $session->addError($this->__('Failed to get access token: %s', $e->getMessage()));
                return $this->_init();
            }
        }

        $this->_renderLayout($this->__('OAuth2 REST Resources'), 'resource');
    }

    /**
     * Exchange authorization code for access token
     */
    protected function _getAccessToken($code)
    {
        $session = Mage::getSingleton('customer/session');
        $host = $session->getOauth2Url();

        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => Mage::getUrl('*/*/callback', ['ssid' => $session->getEncryptedSessionId()]),
            'client_id' => $session->getOauth2ClientId(),
            'client_secret' => $session->getOauth2ClientSecret()
        ];

        $response = $this->_httpPost($host . self::TOKEN_ENDPOINT, $params);
        $data = json_decode($response, true);
        return $data['access_token'];
    }

    /**
     * Perform HTTP POST request
     */
    protected function _httpPost($url, $params)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        return curl_exec($ch);
    }

    protected function _renderLayout($title, $template)
    {
        $this->loadLayout();
        $this->getLayout()->getBlock('head')->setTitle($title);
        $this->renderLayout();
    }

    protected function _redirectUrl($url)
    {
        echo "<script>window.location = '$url';</script>";
        die();
    }
}
