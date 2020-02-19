<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\Cache;
use PHPUnit\Framework\TestCase;
use Vectorface\Cache\Exception\InvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException as IInvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

abstract class GenericCacheTest extends TestCase
{
    /**
     * The cache entry to be set by child classes.
     *
     * @var Cache
     */
    protected $cache;

    public function testClass()
    {
        foreach ($this->getCaches() as $cache) {
            $this->assertTrue($cache instanceof Cache);
            $this->assertTrue($cache instanceof CacheInterface);
        }
    }

    /**
     * @dataProvider cacheDataProvider
     */
    public function testGet($key, $data, $ttl)
    {
        foreach ($this->getCaches() as $cache) {
            $cache->set($key, $data, $ttl); /* Write */
            $this->assertEquals($data, $cache->get($key));

            $cache->set($key, $data . $data, $ttl); /* Overwrite */
            $this->assertEquals($data . $data, $cache->get($key));

            $this->assertNull($cache->get($key . ".unrelated"));
        }
    }

    public function testMultipleOperations()
    {
        $values = [
            'foo' => 'bar',
            'baz' => 'quux',
        ];
        foreach ($this->getCaches() as $cache) {
            $this->assertEquals(
                ['foo' => 'dflt', 'baz' => 'dflt'],
                $cache->getMultiple(array_keys($values), 'dflt')
            , "Expected the result to be populated with default values");
            $this->assertEquals([], $cache->getMultiple([]));
            $this->assertTrue($cache->setMultiple($values));
            $this->assertEquals($values, $cache->getMultiple(array_keys($values)));
            $this->assertTrue($cache->deleteMultiple(array_keys($values)));
            $this->assertTrue($cache->deleteMultiple([]));
            $this->assertEquals(
                ['foo' => 'dflt', 'baz' => 'dflt'],
                $cache->getMultiple(array_keys($values), 'dflt')
            );
        }
    }

    /**
     * @dataProvider cacheDataProvider
     */
    public function testDelete($key, $data, $ttl)
    {
        foreach ($this->getCaches() as $cache) {
            $this->assertTrue($cache->set($key, $data, $ttl));
            $this->assertTrue($cache->delete($key));
            $this->assertNull($cache->get($key));
        }
    }

    public function testHas()
    {
        foreach ($this->getCaches() as $cache) {
            $this->assertFalse($cache->has(__FUNCTION__));
            $this->assertTrue($cache->set(__FUNCTION__, __METHOD__));
            $this->assertTrue($cache->has(__FUNCTION__));
        }
    }

    public function testClean()
    {
        /* Not all caches can clean, so just test that we can try and get a valid success/failure result. */
        foreach ($this->getCaches() as $cache) {
            $this->assertTrue($cache->set('foo', 'bar'));
            $this->assertTrue(is_bool($cache->clean()));
        }
    }

    /**
     * @dataProvider cacheDataProvider
     */
    public function testFlush($key, $data, $ttl)
    {
        $this->realTestFlushAndClear($key, $data, $ttl, true);
    }

    /**
     * @dataProvider cacheDataProvider
     */
    public function testClear($key, $data, $ttl)
    {
        $this->realTestFlushAndClear($key, $data, $ttl, false);
    }

    public function realTestFlushAndClear($key, $data, $ttl, $flush)
    {
        foreach ($this->getCaches() as $cache) {
            $cache->set($key, $data, $ttl);
            $cache->set($key."2", $data, $ttl + 50000);
            $flush ? $cache->flush() : $cache->clear();

            $this->assertNull($cache->get($key));
            $this->assertNull($cache->get($key . ".unrelated"));
        }
    }

    public function testPSR16()
    {
        $expectIAE = function($callback, $message) {
            try {
                $callback();
                $this->fail("$message: Expected exception");
            } catch (InvalidArgumentException $e) {
                $this->assertInstanceOf(
                    IInvalidArgumentException::class,
                    $e,
                    "$message: Expected Psr\SimpleCache\InvalidArgumentException"
                );
            }
        };

        foreach ($this->getCaches() as $cache) {
            $expectIAE(function() use($cache) { $cache->get(new \stdClass()); }, "Invalid key in get");
            $expectIAE(function() use($cache) { $cache->set(new \stdClass(), "value"); }, "Invalid key in set, exception expected");
            $expectIAE(function() use($cache) { $cache->set("key", "value", []); }, "Invalid ttl in " . get_class($cache) . " set, exception expected");
        }
    }

    public function cacheDataProvider()
    {
        return [
            [
                "testKey1",
                "testData1",
                50 * 60
            ],
            [
                "AnotherKey",
                "Here is some more data that I would like to test with",
                3000
            ],
            [
                "IntData",
                17,
                3000
            ],
        ];
    }

    protected function getCaches()
    {
        return is_array($this->cache) ? $this->cache : [$this->cache];
    }
}
