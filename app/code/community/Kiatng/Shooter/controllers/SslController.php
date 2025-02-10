<?php
/**
 * @category   Kiatng
 * @package    Kiatng_Shooter
 * @copyright  Copyright (c) 2025 Ng Kiat Siong
 * @license    GNU GPL v3.0
 */

class Kiatng_Shooter_SslController extends Kiatng_Shooter_Controller_Abstract
{
    /**
     * Test SSL connection
     */
    public function indexAction()
    {
        $host = $this->getRequest()->getParam('url');

        if (!$host) {
            echo 'Missing url param';
            return;
        }

        $data = $this->_testCurlConnection($host);

        $chainStatus = $this->_verifyCertificateChain($data['cert_info']);
        $chain = $chainStatus['chain'];
        $result = [
            'summary' => [
                'status' => [
                    'overall' => $data['info']['ssl_verify_result'] === 0 ? 'Valid' : 'Invalid',
                    'chain' => $chainStatus['verification']['complete'] ? 'Complete' : 'Incomplete',
                    'ocsp' => $this->_getOcspStatus($data['verbose'])
                ],
                'connection' => [
                    'time' => round($data['info']['total_time'] * 1000, 2) . 'ms',
                    'protocol' => $this->_getProtocolName($data),
                    'cipher' => $this->_getCipherSuite($data['verbose'])
                ],
                'warnings' => array_filter([
                    strpos($data['verbose'], 'No OCSP response received') !== false ? 'OCSP revocation check unavailable' : null
                ])
            ],
            'certificates' => [
                'server' => [
                    'subject' => $chain[0]['subject'],
                    'issuer' => $chain[0]['issuer'],
                    'validity' => [
                        'from' => $chain[0]['valid_from'],
                        'to' => $chain[0]['valid_to'],
                        'is_valid' => (
                            strtotime($chain[0]['valid_from']) <= time() &&
                            strtotime($chain[0]['valid_to']) >= time()
                        )
                    ]
                ],
                'intermediate' => [
                    'subject' => $chain[1]['subject'],
                    'issuer' => $chain[1]['issuer'],
                    'validity' => [
                        'from' => $chain[1]['valid_from'],
                        'to' => $chain[1]['valid_to'],
                        'is_valid' => (
                            strtotime($chain[1]['valid_from']) <= time() &&
                            strtotime($chain[1]['valid_to']) >= time()
                        )
                    ]
                ]
            ],
            'cert_verification' => [
                'chain_complete' => $data['chain_verification']['verification']['complete'],
                'chain_issues' => $data['chain_verification']['verification']['issues'],
                'chain_length' => $data['chain_verification']['count'],
                'ssl_verify_result' => $data['info']['ssl_verify_result'],
                'ocsp_verification' => [
                    'status' => $this->_getOcspStatus($data['verbose']),
                    'stapling_supported' => strpos($data['verbose'], 'OCSP response: no response sent') === false,
                    'response_details' => $this->_parseOcspDetails($data['verbose'])
                ],
                'root_authority' => $data['chain_verification']['root_cert']['subject'] ?? 'unknown'
            ],
            'cert_chain' => array_map(function($cert) {
                return [
                    'subject' => $cert['subject'],
                    'issuer' => $cert['issuer'],
                    'valid_from' => $cert['valid_from'],
                    'valid_to' => $cert['valid_to'],
                    'is_valid' => (
                        strtotime($cert['valid_from']) <= time() &&
                        strtotime($cert['valid_to']) >= time()
                    )
                ];
            }, $data['chain_verification']['chain']),
            'system_info' => [
                'ca_bundle' => $data['ca_info'],
                'connection' => array_intersect_key($data['info'], array_flip([
                    'primary_ip',
                    'primary_port',
                    'protocol',
                    'scheme'
                ])),
            ],
            'debug' => [
                'certificates' => $data['cert_info'],
                'verbose_log' => $data['verbose']
            ]
        ];

        return $this->_echo($result, "SSL Certificate Test: $host");
    }

    /**
     * Helper method to test CURL connection
     *
     * @param string $url
     * @param array $extraOpts
     * @param string $testName
     * @return array
     */
    protected function _testCurlConnection($url, $extraOpts = [])
    {
        $ch = curl_init();

        // Get the system's default CA bundle path
        $caInfo = $this->_getSystemCaInfo();

        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CERTINFO => true,
            CURLOPT_VERBOSE => true,
            // Explicitly set the CA bundle
            CURLOPT_CAINFO => $caInfo['path'],
            // Enable certificate chain verification
            CURLOPT_SSL_VERIFYSTATUS => true
        ];

        // Capture CURL verbose output
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);

        curl_setopt_array($ch, $opts + $extraOpts);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        $certInfo = curl_getinfo($ch, CURLINFO_CERTINFO);

        // Verify certificate chain
        $chainVerification = $this->_verifyCertificateChain($certInfo);

        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);

        curl_close($ch);

        return [
            'status' => $response === false ? 'Failed' : 'Success',
            'error' => $error,
            'info' => $info,
            'cert_info' => $certInfo,
            'verbose' => $verboseLog,
            'chain_verification' => $chainVerification,
            'ca_info' => $caInfo
        ];
    }

    /**
     * Get system CA certificate information
     *
     * @return array
     */
    protected function _getSystemCaInfo()
    {
        $possiblePaths = [
            '/etc/ssl/certs/ca-certificates.crt', // Debian/Ubuntu
            '/etc/pki/tls/certs/ca-bundle.crt',   // RHEL/CentOS
            '/etc/ssl/ca-bundle.pem',             // OpenSUSE
            '/usr/local/share/certs/ca-root-nss.crt', // FreeBSD
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return [
                    'path' => $path,
                    'exists' => true,
                    'readable' => is_readable($path),
                    'size' => filesize($path)
                ];
            }
        }

        // Fallback to OpenSSL's default path
        return [
            'path' => openssl_get_cert_locations()['default_cert_file'] ?? null,
            'exists' => false,
            'error' => 'No standard CA bundle found'
        ];
    }

    /**
     * Verify the certificate chain
     *
     * @param array $certInfo
     * @return array
     */
    protected function _verifyCertificateChain($certInfo)
    {
        if (empty($certInfo)) {
            return [
                'chain' => [],
                'verification' => ['complete' => false, 'issues' => ['No certificate information available']],
                'count' => 0,
                'root_cert' => null
            ];
        }

        $chain = [];

        // Build the certificate chain
        foreach ($certInfo as $cert) {
            if (empty($cert['Subject']) || empty($cert['Issuer'])) {
                continue;
            }

            $chain[] = [
                'subject' => $cert['Subject'],
                'issuer' => $cert['Issuer'],
                'valid_from' => $cert['Start date'] ?? null,
                'valid_to' => $cert['Expire date'] ?? null,
                'serial' => $cert['Serial Number'] ?? null
            ];
        }

        // Verify chain integrity
        $chainStatus = ['complete' => false, 'issues' => []];

        for ($i = 0; $i < count($chain) - 1; $i++) {
            $current = $chain[$i];
            $issuer = $chain[$i + 1];

            // Check if the current cert's issuer matches the next cert's subject
            if ($current['issuer'] !== $issuer['subject']) {
                $chainStatus['issues'][] = sprintf(
                    "Break in chain: Certificate '%s' is issued by '%s' but next certificate subject is '%s'",
                    $current['subject'],
                    $current['issuer'],
                    $issuer['subject']
                );
            }

            // Check certificate dates
            $now = time();
            $validFrom = strtotime($current['valid_from']);
            $validTo = strtotime($current['valid_to']);

            if ($now < $validFrom) {
                $chainStatus['issues'][] = "Certificate not yet valid: " . $current['subject'];
            }
            if ($now > $validTo) {
                $chainStatus['issues'][] = "Certificate expired: " . $current['subject'];
            }
        }

        $chainStatus['complete'] = empty($chainStatus['issues']);

        return [
            'chain' => $chain,
            'verification' => $chainStatus,
            'count' => count($chain),
            'root_cert' => end($chain)
        ];
    }

    protected function _getProtocolName($data)
    {
        // Get protocol from verbose log first as it's more accurate
        if (preg_match('/SSL connection using (TLSv[\d\.]+)/', $data['verbose'], $matches)) {
            return $matches[1];
        }

        // Fallback to protocol number mapping
        $protocols = [
            2 => 'TLSv1.2',
            3 => 'TLSv1.3',
        ];
        $protocol = $data['info']['protocol'];
        return $protocols[$protocol] ?? "Unknown ($protocol)";
    }

    protected function _getOcspStatus($verboseLog)
    {
        if (strpos($verboseLog, 'No OCSP response received') !== false) {
            return 'No Response';
        }
        if (strpos($verboseLog, 'OCSP response received') !== false) {
            return 'Success';
        }
        return 'Unknown';
    }

    protected function _parseOcspDetails($verboseLog)
    {
        $details = [];

        // Check if OCSP stapling is attempted
        if (strpos($verboseLog, 'OCSP stapling:') !== false) {
            $details['stapling_attempted'] = true;
        }

        // Extract response time if available
        if (preg_match('/OCSP response received after (\d+)ms/', $verboseLog, $matches)) {
            $details['response_time_ms'] = (int)$matches[1];
        }

        return $details;
    }

    protected function _getCipherSuite($verboseLog)
    {
        if (preg_match('/\* SSL connection using (?:TLSv[\d\.]+) \/ ([^\n]+)/', $verboseLog, $matches)) {
            return trim($matches[1]);
        }
        return 'Unknown';
    }
}
