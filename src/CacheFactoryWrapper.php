<?php

namespace Drupal\cache_metrics;

use Drupal\Core\Cache\CacheFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Wraps a cache factory to register all calls to the cache system.
 *
 * Inspired by webprofiler.
 */
class CacheFactoryWrapper implements CacheFactoryInterface, ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * The cache factory.
   *
   * @var \Drupal\Core\Cache\CacheFactoryInterface
   */
  protected $cacheFactory;

  /**
   * All wrapped cache backends.
   *
   * @var \Drupal\cache_metrics\CacheBackendWrapper[]
   */
  protected $cacheBackends = [];

  /**
   * A container parameter for disabling New Relic even when the extension is present.
   *
   * @var bool
   */
  protected $newRelicEnabled;

  /**
   * Creates a new CacheFactoryWrapper instance.
   *
   * @param \Drupal\Core\Cache\CacheFactoryInterface $cache_factory
   *   The cache factory.
   * @param bool $newRelicEnabled
   */
  public function __construct(CacheFactoryInterface $cache_factory, bool $newRelicEnabled) {
    $this->cacheFactory = $cache_factory;
    $this->newRelicEnabled = $newRelicEnabled;
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    if (!$this->isNewRelicEnabled()) {
      // If disabled, return an unwrapped backend.
      return $this->cacheFactory->get($bin);
    }

    if (!isset($this->cacheBackends[$bin])) {
      $cache_backend = $this->cacheFactory->get($bin);
      $this->cacheBackends[$bin] = new CacheBackendWrapper($cache_backend, $bin);
    }
    return $this->cacheBackends[$bin];
  }

  /**
   * Use services.yml parameter to disable NR if you have the NR extension but dont want this logging.
   *
   * @return bool
   */
  public function isNewRelicEnabled() {
    return $this->newRelicEnabled && function_exists('newrelic_record_custom_event');
  }

}
