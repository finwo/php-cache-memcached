<?php

namespace Finwo\Cache;

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

    /**
     * @return \Memcached
     */
    protected function getMemcached()
    {
        if (null === $this->memcached) {
            $this->memcached = new \Memcached();
            $this->memcached->addServer($this->server, $this->port);
        }

        return $this->memcached;
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
        $key_lock = \md5(\sprintf("%s_lock", $key));
        $key_data = \md5(\sprintf("%s_data", $key));
        $key_ttl  = \md5(\sprintf("%s_ttl",  $key));

        // Fetch memcached object
        $memcached = $this->getMemcached();

        // Fetch data
        $data_data = $memcached->get($key_data);

        // Return data if the current TTL is still valid
        if ($memcached->get($key_ttl)) {
            return $data_data;
        }

        // Return data if we're already "refreshing"
        if ($memcached->get($key_lock)) {
            return $data_data;
        }

        // Mark we're refreshing for ttl/10
        // Also give it 2 seconds, because some operations take time
        $memcached->add($key_lock, 'Hello World', max(2,$ttl/10));

        // And return that we don't have recent data
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function store($key = '', $value, $ttl = 30)
    {
        // Generate seperate keys for storing
        $key_data = \md5(\sprintf("%s_data", $key));
        $key_ttl  = \md5(\sprintf("%s_ttl",  $key));

        // Pre-generate time
        $time_data = \max(3600, $ttl*10);
        if ($ttl === 0) {
            $time_data = 0;
        }

        // Fetch memcached object
        $memcached = $this->getMemcached();

        // Store data
        if($memcached->get($key_data)) {
            $memcached->replace($key_data, $value, $time_data);
        } else {
            $memcached->add($key_data, $value, $time_data);
        }

        // Store TTL
        $memcached->add($key_ttl, 'Hello World', $ttl);

        // Return ourselves
        return $this;
    }
}
