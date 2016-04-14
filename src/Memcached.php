<?php

namespace Fiwno\Cache;

use Finwo\Cache\Cache;

/**
 * Implements memcached
 * Handles refreshing in a server-friendly way
 */
class Memcached extends Cache
{
    /**
     * @var \Memcached
     */
    protected $memcached;

    /**
     * @var string
     */
    protected $server = '127.0.0.1';

    /**
     * @var int
     */
    protected $port = 11211;

    protected function getMemcached()
    {
        if (null === $this->memcached) {
            $this->memcached = new \Memcached();
            $this->memcached->addServer($this->server, $this->port);
        }
    }

    public function __construct($options = array())
    {
        // Override default options
        foreach($options as $key => $value) {
            if (isset($this->{$key})) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($key = '', $ttl = 30)
    {
        // Generate seperate keys for fetching
        $key_lock = sprintf("%s_lock", $key);
        $key_data = sprintf("%s_data", $key);
        $key_ttl  = sprintf("%s_ttl",  $key);

        // Fetch TTL
        $data_data = $this->memcached->get($key_data);

        // Return data if the current TTL is still valid
        if ($this->memcached->get($key_ttl)) {
            return $data_data;
        }

        // Return data if we're already "refreshing"
        if ($this->memcached->get($key_lock)) {
            return $data_data;
        }

        // Mark we're refreshing for ttl/10
        // Also give it 2 seconds, because some operations take time
        $this->memcached->add($key_lock, true, max(2,$ttl/10));

        // And return that we don't have recent data
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function store($key = '', $value, $ttl = 30)
    {
        // Generate seperate keys for storing
        $key_data = sprintf("%s_data", $key);
        $key_ttl  = sprintf("%s_ttl",  $key);

        // Store data
        $this->memcached->add($key_data, $value, max(3600, $ttl*10));

        // Store TTL
        $this->memcached->add($key_ttl, true, $ttl);
    }
}
