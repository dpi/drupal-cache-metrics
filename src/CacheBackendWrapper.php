<?php

namespace Drupal\cache_metrics;

use Drupal\Component\Utility\Timer;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

/**
 * Wraps an existing cache backend to track calls to the cache backend.
 *
 * Inspired by webprofiler module.
 */
class CacheBackendWrapper implements CacheBackendInterface, CacheTagsInvalidatorInterface {

  const EVENT_NAME = 'CacheGet';

  /**
   * The wrapped cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The name of the wrapped cache bin.
   *
   * @var string
   */
  protected $bin;

  /**
   * Constructs a new CacheBackendWrapper.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The wrapped cache backend.
   * @param string $bin
   *   The name of the wrapped cache bin.
   */
  public function __construct(CacheBackendInterface $cacheBackend, $bin) {
    $this->cacheBackend = $cacheBackend;
    $this->bin = $bin;
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    Timer::start($cid);
    $cache = $this->cacheBackend->get($cid, $allow_invalid);
    $duration = round(Timer::stop($cid)['time']);

    $attributes = [
      'duration' => $duration,
      'cid' => $cid,
      'hit_or_miss' => $cache ? 'hit' : 'miss',
      'expire' => $cache ? $cache->expire : '',
      'tags' => $cache ? implode(' ', $cache->tags) : '',
      'isMultiple' => FALSE,
    ];
    newrelic_record_custom_event(self::EVENT_NAME, $attributes);

    return $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    $cidsCopy = $cids;
    // Perform the actual fetch.
    $cache = $this->cacheBackend->getMultiple($cids, $allow_invalid);

    // Record an event for each cid that was requested.
    foreach ($cidsCopy as $cid) {
      $hit = !in_array($cid, $cids);
      $attributes = [
        // Not possible to measure duration for getMultiple().
        'duration' => '',
        'cid' => $cid,
        'hit_or_miss' => $hit ? 'hit' : 'miss',
        'expire' => $hit ? $cache[$cid]->expire : '',
        'tags' => $hit ? implode(' ', $cache[$cid]->tags) : '',
        'isMultiple' => TRUE,
      ];
      newrelic_record_custom_event(self::EVENT_NAME, $attributes);
    }

    return $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []) {
    return $this->cacheBackend->set($cid, $data, $expire, $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    return $this->cacheBackend->setMultiple($items);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    return $this->cacheBackend->delete($cid);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    return $this->cacheBackend->deleteMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    return $this->cacheBackend->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    return $this->cacheBackend->invalidate($cid);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    return $this->cacheBackend->invalidateMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    if ($this->cacheBackend instanceof CacheTagsInvalidatorInterface) {
      $this->cacheBackend->invalidateTags($tags);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    return $this->cacheBackend->invalidateAll();
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    return $this->cacheBackend->garbageCollection();
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    return $this->cacheBackend->removeBin();
  }

}
