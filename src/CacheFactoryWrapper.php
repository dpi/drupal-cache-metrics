<?php

namespace Drupal\cache_metrics;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * A container parameter for disabling cache hit/miss logging for certain bins.
   *
   * @var array
   */
  protected $blacklist;

  /**
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Creates a new CacheFactoryWrapper instance.
   *
   * @param \Drupal\Core\Cache\CacheFactoryInterface $cache_factory
   *   The cache factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   * @param array $blacklist
   */
  public function __construct(CacheFactoryInterface $cache_factory, AccountProxyInterface $currentUser, RequestStack $requestStack, array $blacklist) {
    $this->cacheFactory = $cache_factory;
    $this->blacklist = $blacklist;
    $this->currentUser = $currentUser;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    if (!$this->isEnabled($bin)) {
      // If disabled, return an unwrapped backend.
      return $this->cacheFactory->get($bin);
    }

    if (!isset($this->cacheBackends[$bin])) {
      $cache_backend = $this->cacheFactory->get($bin);
      $this->cacheBackends[$bin] = new CacheBackendWrapper($cache_backend, $bin, $this->currentUser, $this->requestStack);
    }
    return $this->cacheBackends[$bin];
  }

  /**
   * Use services.yml parameter to disable logging for certain bins, or '*' for all bins.
   *
   * @param string $bin
   *
   * @return bool
   */
  public function isEnabled($bin) {
    return !in_array('*', $this->blacklist) && !in_array($bin, $this->blacklist) && function_exists('newrelic_record_custom_event');
  }

}
