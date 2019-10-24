<?php
namespace Drupal\cache_metrics;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class CacheMetricsCacheTagsInvalidator.
 *
 * @package Drupal\cache_metrics
 */
class CacheMetricsCacheTagsInvalidator implements CacheTagsInvalidatorInterface {

  protected $logger;

  /**
   * CacheMetricsCacheTagsInvalidator constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   A logger factory.
   */
  public function __construct(LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->logger = $loggerChannelFactory->get('cache_metrics');
  }

  /**
   * Log all Invalidations.
   *
   * @param string[] $tags
   *   The list of tags for which to invalidate cache items.
   */
  public function invalidateTags(array $tags) {
    $this->logger->debug(t('Invalidating the following tags: @tags', ['@tags' => implode(' ', $tags)]));
    if (function_exists('newrelic_record_custom_event')) {
      $this->logger->warning('NR *is* installed');
      // We don't use Monolog's NR handler because it just sets attributes on an existing event. See \Monolog\Handler\NewRelicHandler.
      // We can't record just one event because https://discuss.newrelic.com/t/how-to-send-multiple-items-in-a-custom-attribute/9280/5.
      foreach ($tags as $tag) {
        newrelic_record_custom_event('invalidateTag', ['tag' => $tag]);
      }
    }
    else {
      // @todo remove
      $this->logger->warning('NR not installed');
    }
  }

}
