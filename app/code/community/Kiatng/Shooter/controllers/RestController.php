<?php
/**
 * @category   Kiatng
 * @package    Kiatng_Shooter
 * @copyright  Copyright (c) 2025 Ng Kiat Siong
 * @license    GNU GPL v3.0
 */

class Kiatng_Shooter_RestController extends Kiatng_Shooter_Controller_Abstract
{
    const STATE_INIT = 0;
    const STATE_REQUEST_TOKEN = 1;
    const STATE_ACCESS_TOKEN = 2;
    const STATE_RESOURCE = 3;

    public function preDispatch()
    {
        /**
         * Check if we lost the session at callback.
         * URL: domain/shooter/rest/callback/ssid/37teecslrt5k6q40if0nrqel7e/?oauth_token=random&oauth_verifier=random
         * @link https://stackoverflow.com/questions/22079477/session-is-lost-after-an-oauth-redirect
         */
        if (
            $this->getRequest()->getActionName() === 'callback'
            && !Mage::getSingleton('customer/session')->isLoggedIn()
            && $sid = $this->getRequest()->getParam('ssid')
        ) {
            // Set the session in browser's cookie.
            Mage::getSingleton('core/cookie')->set(self::SESSION_NAMESPACE, $sid);
            // Use js to redirect in browser to restore the session, other redirect methods will lose the session.
            $url = Mage::getUrl('*/*', ['_current' => true, '_use_rewrite' => true, '_query' => $_GET]);
            echo "<script>window.location = '$url';</script>";
            die();
        }

        parent::preDispatch();
    }

    /**
     * Get OAuth consumer from session.
     *
     * @return Zend_Oauth_Consumer
     */
    protected function _getConsumer(): ?Zend_Oauth_Consumer
    {
        return Mage::getSingleton('customer/session')->getOauthConsumer();
    }

    /**
     * Entry point to OAuth.
     */
    public function indexAction()
    {
        $session = Mage::getSingleton('customer/session');
        $state = $session->getOauthConsumer() ? $session->getOauthState() : self::STATE_INIT;
        switch ($state) {
            case self::STATE_INIT:
                $this->_init();
                break;
            case self::STATE_REQUEST_TOKEN:
                $this->_requestToken();
                break;
            case self::STATE_ACCESS_TOKEN:
                $this->callbackAction();
                break;
            case self::STATE_RESOURCE:
                // Render resource page.
                $this->_renderLayout($this->__('shooter REST Resources'), 'resource');
                break;
        }
    }

    /**
     * Callback from OAuth host. Get access token from OAuth host and render resource page.
     * http://openmage.site/shooter/rest/callback/?oauth_token=randonstring&oauth_verifier=randonstring
     */
    public function callbackAction()
    {
        // Get access token from OAuth host.
        $session = Mage::getSingleton('customer/session');
        $session->setOauthAccessTokenGet($_GET);
        if (!$session->getOauthAccessToken()) {
            $consumer = $this->_getConsumer();
            $requestToken = $consumer->getLastRequestToken() ?? $session->getOauthRequestToken();
            try {
                $accessToken = $consumer->getAccessToken($_GET, $requestToken);
            } catch (Throwable $e) {
                $session->addError($this->__('Problem getting access token from OAuth host: %s. Please try again.', $e->getMessage()));
                return $this->_init();
            }
            $session->setOauthAccessToken($accessToken);
            $session->setOauthState(self::STATE_RESOURCE);
        }

        $this->_renderLayout($this->__('shooter REST Resources'), 'resource');
    }

    /**
     * Callback from OAuth host. User rejected the OAuth request.
     * Configure the "Rejected Callback URL" in OM Backend > System > Configuration > Web Services > REST - OAuth Consumers > select a consumer.
     * An example of the URL: https://openmage.site/shooter/rest/reject
     */
    public function rejectAction()
    {
        Mage::getSingleton('customer/session')->addNotice($this->__('You have rejected the OAuth request.'));
        $this->_init();
    }

    /**
     * Save OAuth params to session.
     */
    public function oauthPostAction()
    {
        $host = rtrim($this->getRequest()->getPost('url', ''), '/');
        if (!$host) {
            return $this->_redirect('*/*/new');
        }

        $session = Mage::getSingleton('customer/session');
        $session->setOauthUrl($host);
        $session->setOauthKey($this->getRequest()->getPost('key'));
        $session->setOauthSecret($this->getRequest()->getPost('secret'));
        $session->setOauthUserType($this->getRequest()->getPost('user_type'));
        $session->setOauthState(self::STATE_REQUEST_TOKEN);

        $consumer = new Zend_Oauth_Consumer([
            'siteUrl' => "{$host}/oauth",
            'requestTokenUrl' => "{$host}/oauth/initiate",
            'accessTokenUrl' => "{$host}/oauth/token",
            'authorizeUrl' => $session->getOauthUserType() === 'admin'
                ? "{$host}/admin/oauth_authorize"
                : "{$host}/oauth/authorize",
            'consumerKey' => $session->getOauthKey(),
            'consumerSecret' => $session->getOauthSecret(),
            //'callbackUrl' => Mage::getUrl('*/*/callback')
            'callbackUrl' => Mage::getUrl('*/*/callback', ['ssid' => $session->getEncryptedSessionId()])
        ]);
        $session->setOauthConsumer($consumer);

        $this->_redirect('*/*');
    }

    /**
     * Init session's OAuth data. Render OAuth form.
     */
    protected function _init()
    {
        $session = Mage::getSingleton('customer/session');
        foreach (Mage::getSingleton('customer/session')->getData() as $k => $v) {
            if (strpos($k, 'oauth') === 0) {
                $session->unsetData($k);
            }
        }
        $session->setOauthState(self::STATE_INIT);
        $this->_renderLayout($this->__('Test OAuth 1.0a'), 'oauth');
    }

    /**
     * Get request token from OAuth host.
     * https://openmage.site/oauth/authorize?oauth_token=9ac3d537fc0273b1f1a708b9cf0402bb
     */
    protected function _requestToken()
    {
        $session = Mage::getSingleton('customer/session');
        $session->setOauthRequestTokenGet($_GET);
        $consumer = $this->_getConsumer();
        try {
            $requestToken = $consumer->getRequestToken();
        } catch (Zend_Oauth_Exception $e) {
            $url = $session->getOauthUrl();
            $errMsg = $this->__('Problem getting request token from %s.<br>', $url);
            $errMsg .= $e->getMessage();
            if ($e->getPrevious()) {
                $errMsg .= '<br><b>Previous error:</b> ' . $e->getPrevious()->getMessage();
            }
            $session->addError($errMsg);
            return $this->_redirect('*/*/new');
        } catch (Throwable $e) {
            $url = $session->getOauthUrl();
            $errMsg = $this->__('Problem getting request token from %s. Make sure you input the correct params.<br>', $url);
            $errMsg .= $e->getMessage();
            $session->addError($errMsg);
            return $this->_redirect('*/*/new');
        }
        $session->setOauthRequestToken($requestToken);
        $session->setOauthState(self::STATE_ACCESS_TOKEN);
        $consumer->redirect(); // Redirect to host for authorization.
    }

    /**
     * Get OAuth client from session.
     *
     * @return Zend_Oauth_Client
     */
    protected function _getOauthClient()
    {
        $session = Mage::getSingleton('customer/session');
        if (!$session->getOauthClient()) {
            $session->setOauthAccessResourceGet($_GET);
            $host = $session->getOauthUrl();
            $oauthOptions = [
                'siteUrl' => "$host/oauth",
                'requestTokenUrl' => "$host/oauth/initiate",
                'accessTokenUrl' => "$host/oauth/token",
                'consumerKey' => $session->getOauthKey(),
                'consumerSecret' => $session->getOauthSecret(),
            ];
            $client = $this->_getConsumer()->getLastAccessToken()->getHttpClient($oauthOptions);
            $client->setHeaders('Accept', 'application/json');
            $session->setOauthClient($client);
        }

        return $session->getOauthClient();
    }

    /**
     * Access resource from OAuth host.
     */
    public function ajaxResourceAction()
    {
        $host = Mage::getSingleton('customer/session')->getOauthUrl();
        if (!$host) {
            return $this->_redirect('*/*/new');
        }

        $resource = $this->getRequest()->getParam('name', 'products');
        $path = $this->getRequest()->getParam('path', '');
        $method = $this->getRequest()->getParam('method', 'GET');
        $params = $this->getRequest()->getParam('params');
        if ($params && ($method === 'PUT' || $method === 'POST')) {
            $params = json_decode($params, true);
        }

        $client = $this->_getOauthClient();
        $client->setMethod($method);
        if (is_array($params)) {
            //$client->setParameterPost($params); // Mage_Api2_Exception: Server can not understand Content-Type HTTP header media type "application/x-www-form-urlencoded"
            $client->setRawData(json_encode($params), 'application/json');
        }
        $client->setUri("$host/$path/$resource");
        $response = $client->request();

        $echo = json_encode([
            'json_body' => json_decode($response->getBody(), true),
            'headers' => $response->getHeaders(),
            'status' => $response->getStatus(),
            'message' => $response->getMessage(),
            //'raw_body' => $response->getRawBody(), // May contains non-UTF8 characters.
        ]);

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody($echo);
    }

    /**
     * New OAuth session.
     */
    public function newAction()
    {
        $this->_init();
    }

    /**
     * @param string $title
     * @param string $blockName
     */
    protected function _renderLayout($title, $blockName)
    {
        $this->loadLayout(['default', 'page_one_column']);
        $this->_initLayoutMessages('customer/session');
        $layout = $this->getLayout();
        $layout->getBlock('head')->setTitle($title);
        $layout->getBlock('content')->append(
            $layout->createBlock(
                'core/template',
                "shooter_rest_$blockName",
                ['template' => "shooter/rest/{$blockName}.phtml"]
            )
        );
        /** @var Mage_Page_Block_Html $root */
        $root = $layout->getBlock('root');
        $root->unsetChild('header');
        $root->unsetChild('footer');
        $this->renderLayout();
    }

    /**
     * Show session data.
     */
    public function infoAction()
    {
        $data = ['sid' => Mage::getSingleton('core/session')->getEncryptedSessionId()];
        foreach (Mage::getSingleton('customer/session')->getData() as $k => $v) {
            if (strpos($k, 'oauth') === 0) {
                $data[$k] = $v;
            }
        }
        $this->_echo($data, 'Session Data');
    }

    /**
     * Set OAuth state.
     */
    public function stateAction()
    {
        $state = (int) $this->getRequest()->getParam('state', 1);
        Mage::getSingleton('customer/session')->setOauthState($state);
        $this->_redirect('*/*');
    }

    /**
     * Test SSL connection to OAuth server
     */
    public function testSslAction()
    {
        $session = Mage::getSingleton('customer/session');
        $host = $session->getOauthUrl() ?: $this->getRequest()->getParam('url');

        if (!$host) {
            echo "Please provide a URL via parameter or OAuth session";
            return;
        }

        $results = [];

        // Test 1: Basic CURL connection
        $results[] = $this->_testCurlConnection($host);

        // Test 2: Try different SSL versions
        $sslVersions = [
            CURL_SSLVERSION_DEFAULT => 'Default',
            CURL_SSLVERSION_TLSv1 => 'TLS 1.0',
            CURL_SSLVERSION_TLSv1_1 => 'TLS 1.1',
            CURL_SSLVERSION_TLSv1_2 => 'TLS 1.2'
        ];

        foreach ($sslVersions as $version => $label) {
            $results[] = $this->_testCurlConnection($host, [
                CURLOPT_SSLVERSION => $version
            ], "CURL with $label");
        }

        // Test 3: Using Zend_Http_Client
        try {
            $client = new Zend_Http_Client($host);
            $client->request();
            $results[] = [
                'test' => 'Zend_Http_Client',
                'status' => 'Success',
                'info' => $client->getLastResponse()->getStatus() . ' ' . $client->getLastResponse()->getMessage()
            ];
        } catch (Exception $e) {
            $results[] = [
                'test' => 'Zend_Http_Client',
                'status' => 'Failed',
                'error' => $e->getMessage()
            ];
        }

        // Output results
        echo "<pre>";
        echo "SSL Connection Tests to: $host\n\n";
        echo "PHP Version: " . phpversion() . "\n";
        echo "CURL Version: " . curl_version()['version'] . "\n";
        echo "OpenSSL Version: " . OPENSSL_VERSION_TEXT . "\n\n";

        foreach ($results as $result) {
            echo str_repeat("-", 50) . "\n";
            echo "Test: {$result['test']}\n";
            echo "Status: {$result['status']}\n";
            if (isset($result['error'])) {
                echo "Error: {$result['error']}\n";
            }
            if (isset($result['info'])) {
                echo "Info: {$result['info']}\n";
            }
            echo "\n";
        }
        echo "</pre>";
    }

    /**
     * Helper method to test CURL connection
     *
     * @param string $url
     * @param array $extraOpts
     * @param string $testName
     * @return array
     */
    protected function _testCurlConnection($url, $extraOpts = [], $testName = 'Basic CURL')
    {
        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,  // Verify the certificate's name against host
            CURLOPT_CERTINFO => true,     // Get certificate info
            CURLOPT_VERBOSE => true
        ];

        // Capture CURL verbose output
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);

        curl_setopt_array($ch, $opts + $extraOpts);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);

        // Get certificate information
        $certInfo = curl_getinfo($ch, CURLINFO_CERTINFO);

        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);

        curl_close($ch);

        if ($response === false) {
            return [
                'test' => $testName,
                'status' => 'Failed',
                'error' => $error,
                'info' => "Verbose log:\n" . $verboseLog
            ];
        }

        // Format certificate chain information
        $certChainInfo = '';
        if (!empty($certInfo)) {
            foreach ($certInfo as $key => $cert) {
                $certChainInfo .= sprintf(
                    "\nCertificate #%d:\n" .
                    "Subject: %s\n" .
                    "Issuer: %s\n" .
                    "Valid Until: %s\n",
                    $key + 1,
                    $cert['Subject'] ?? 'N/A',
                    $cert['Issuer'] ?? 'N/A',
                    $cert['Expire date'] ?? 'N/A'
                );
            }
        }

        return [
            'test' => $testName,
            'status' => 'Success',
            'info' => "HTTP {$info['http_code']}, SSL: {$info['ssl_verify_result']}\n" .
                     "Certificate Chain:" . $certChainInfo . "\n" .
                     "Verbose log:\n" . $verboseLog
        ];
    }
}
