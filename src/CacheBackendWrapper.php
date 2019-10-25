<?php

namespace Drupal\cache_metrics;

use Drupal\Component\Utility\Timer;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new CacheBackendWrapper.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The wrapped cache backend.
   * @param string $bin
   *   The name of the wrapped cache bin.
   */
  public function __construct(CacheBackendInterface $cacheBackend, $bin, AccountProxyInterface $currentUser, RequestStack $requestStack) {
    $this->cacheBackend = $cacheBackend;
    $this->bin = $bin;
    $this->currentUser = $currentUser;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    $cache = $this->cacheBackend->get($cid, $allow_invalid);

    $request = $this->requestStack->getCurrentRequest();
    $attributes = [
      // Granular duration not possible for getMultiple so omitted for now.
      'duration' => NULL,
      'cid' => $cid,
      'bin' => $this->bin,
      'hit' => (int) $cache,
      'miss' => (int) !$cache,
      'expire' => $cache ? $cache->expire : NULL,
      'tags' => $cache ? implode(' ', $cache->tags) : NULL,
      'isMultiple' => FALSE,
      'uri' => $request->getBaseUrl() . $request->getPathInfo(),
      // Acquia uses this to identify a request. https://docs.acquia.com/acquia-cloud/develop/env-variable/
      'request_id' => getenv('HTTP_X_REQUEST_ID'),
      // A Cloudflare trace header.
      'cf_ray' => $this->requestStack->getCurrentRequest()->headers->get('CF-RAY'),
      'uid' => $this->currentUser->id(),
    ];
    $this->record($attributes);

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
      $request = $this->requestStack->getCurrentRequest();
      $attributes = [
        // Not possible to measure duration for getMultiple().
        'duration' => NULL,
        'cid' => $cid,
        'bin' => $this->bin,
        'hit' => (int) $hit,
        'miss' => (int) !$hit,
        'expire' => $hit ? $cache[$cid]->expire : NULL,
        'tags' => $hit ? implode(' ', $cache[$cid]->tags) : NULL,
        'isMultiple' => TRUE,
        'uri' => $request->getBaseUrl() . $request->getPathInfo(),
        // Acquia https://docs.acquia.com/acquia-cloud/develop/env-variable.
        'request_id' => getenv('HTTP_X_REQUEST_ID'),
        // A Cloudflare header.
        'cf_ray' => $this->requestStack->getCurrentRequest()->headers->get('CF-RAY'),
        'uid' => $this->currentUser->id(),
      ];
      $this->record($attributes);
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

  /**
   * Record the event.
   *
   * @param array $attributes
   */
  protected function record(array $attributes) {
    newrelic_record_custom_event(self::EVENT_NAME, $attributes);
  }

}
