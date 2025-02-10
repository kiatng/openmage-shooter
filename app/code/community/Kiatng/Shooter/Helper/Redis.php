<?php
/**
 * @category   Kiatng
 * @package    Kiatng_Shooter
 * @copyright  Copyright (c) 2025 Ng Kiat Siong
 * @license    GNU GPL v3.0
 */
class Kiatng_Shooter_Helper_Redis
{
    /**
     * Get the Redis session handler
     *
     * @return Cm\RedisSession\Handler|string
     */
    protected function _getRedisSessionHandler()
    {
        $getHandler = function() {
            /** @var Cm_RedisSession_Model_Session $this */
            return $this->sessionHandler;
        };
        try {
            $object = Mage::getSingleton('cm_redissession/session');
            return $getHandler->call($object);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Get credis client
     *
     * @param Cm_Cache_Backend_Redis|\Cm\RedisSession\Handler $object
     * @return Credis_Client|null
     */
    protected function _getCredisClient($object)
    {
        $client = function() {
            /** @var Cm_Cache_Backend_Redis|Cm\RedisSession\Handler $this */
            return $this->_redis;
        };
        return $client->call($object);
    }

    /**
     * Get Redis client info
     */
    protected function _getRedisClientInfo(Credis_Client $redis): array
    {
        return [
            'host' => $redis->getHost(),
            'port' => $redis->getPort(),
            'selected_db' => $redis->getSelectedDb(),
            'ssl_meta' => $redis->getSslMeta(),
            'is_tls' => $redis->isTls(),
            'is_persistent' => $redis->getPersistence(),
            'is_subscribed' => $redis->isSubscribed(),
            'is_connected' => $redis->isConnected(),
            'info' => $redis->info(),
        ];
    }

    /**
     * Check Redis status:
     *  1. Session Redis
     *  2. Cache Redis
     */
    public function info()
    {
        $result = [];

        // Check Session Redis
        try {
            $sessionHandler = $this->_getRedisSessionHandler();
            if ($sessionHandler instanceof Cm\RedisSession\Handler) {
                $redis = $this->_getCredisClient($sessionHandler);
                $info = $redis->info();
                $result['session_redis'] = [
                    'status' => 'Connected',
                    'failed_lock_attempts' => Cm_RedisSession_Model_Session::$failedLockAttempts,
                    'stats' => [
                        'used_memory_human' => $info['used_memory_human'],
                        'used_memory_peak_human' => $info['used_memory_peak_human'],
                        'connected_clients' => $info['connected_clients'],
                        'uptime_in_days' => $info['uptime_in_days'],
                        'hits' => $info['keyspace_hits'],
                        'misses' => $info['keyspace_misses'],
                        'hit_rate' => $info['keyspace_hits'] + $info['keyspace_misses'] > 0
                            ? round($info['keyspace_hits'] * 100 / ($info['keyspace_hits'] + $info['keyspace_misses']), 2) . '%'
                            : '0%',
                        'total_connections_received' => $info['total_connections_received'],
                        'total_commands_processed' => $info['total_commands_processed']
                    ],
                    'client' => $this->_getRedisClientInfo($redis),
                    'config' => Mage::getConfig()->getNode('global/redis_session')
                        ? Mage::getConfig()->getNode('global/redis_session')->asArray()
                        : null,
                    'database_stats' => $redis->info('keyspace')
                ];

                // Get session specific stats
                $redis->select($redis->getSelectedDb());
                $result['session_redis']['session_count'] = $redis->dbSize();
            } else {
                $result['session_redis'] = [
                    'status' => $sessionHandler,
                ];
            }
        } catch (Exception $e) {
            $result['session_redis'] = [
                'status' => 'Error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        // Check Cache Redis
        try {
            $cache = Mage::app()->getCache();
            $backend = $cache->getBackend();
            if ($backend instanceof Cm_Cache_Backend_Redis) {
                $redis = $this->_getCredisClient($backend);
                $info = $redis->info();
                $result['cache_redis'] = [
                    'status' => 'Connected',
                    'stats' => [
                        'used_memory_human' => $info['used_memory_human'],
                        'used_memory_peak_human' => $info['used_memory_peak_human'],
                        'memory_fragmentation_ratio' => $info['mem_fragmentation_ratio'],
                        'connected_clients' => $info['connected_clients'],
                        'uptime_in_days' => $info['uptime_in_days'],
                        'hits' => $info['keyspace_hits'],
                        'misses' => $info['keyspace_misses'],
                        'hit_rate' => $backend->getHitMissPercentage() . '%',
                        'memory_usage' => $backend->getFillingPercentage() . '%',
                        'evicted_keys' => $info['evicted_keys'],
                        'total_connections_received' => $info['total_connections_received'],
                        'total_commands_processed' => $info['total_commands_processed']
                    ],
                    'client' => $this->_getRedisClientInfo($redis),
                    'config' => Mage::getConfig()->getNode('global/cache')->asArray(),
                    'database_stats' => $redis->info('keyspace')
                ];

                // Get cache specific stats
                $cacheDbIndex = (int) Mage::getConfig()->getNode('global/cache/database');
                $redis->select($cacheDbIndex);
                $result['cache_redis']['cache_count'] = $redis->dbSize();

            } else {
                $result['cache_redis'] = [
                    'status' => 'Not Using Redis Cache Backend',
                    'current_backend' => get_class($backend)
                ];
            }
        } catch (Exception $e) {
            $result['cache_redis'] = [
                'status' => 'Error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        return $result;
    }
}
