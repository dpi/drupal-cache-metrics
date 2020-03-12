<?php
namespace Drupal\cache_metrics;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class CacheMetricsCacheTagsInvalidator.
 *
 * @package Drupal\cache_metrics
 */
class CacheMetricsCacheTagsInvalidator implements CacheTagsInvalidatorInterface {

  protected $logger;

  /**
   * @var bool
   */
  protected $isEnabled;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * A list of tags that have already been invalidated in this request.
   *
   * Used to prevent the recording of the same cache tag multiple times.
   *
   * @var string[]
   */
  protected $invalidatedTags = [];

  /**
   * CacheMetricsCacheTagsInvalidator constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   A logger factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   * @param bool $isEnabled
   */
  public function __construct(LoggerChannelFactoryInterface $loggerChannelFactory, RequestStack $requestStack, AccountProxyInterface $currentUser, bool $isEnabled) {
    $this->logger = $loggerChannelFactory->get('cache_metrics');
    $this->isEnabled = $isEnabled;
    $this->requestStack = $requestStack;
    $this->currentUser = $currentUser;
  }

  /**
   * Log all Invalidations.
   *
   * @param string[] $tags
   *   The list of tags for which to invalidate cache items.
   */
  public function invalidateTags(array $tags) {
    try {
      $this->logger->debug(t('Invalidating the following tags: @tags', ['@tags' => implode(' ', array_unique($tags))]));
    }
    catch (DatabaseExceptionWrapper $e) {
      // Under rare circumstances a database exception will be thrown.
      // Specifically if you attempt to uninstall a logging module backed by the
      // database, eg. dblog. In that case, the DB schema is removed, followed
      // by some tag invalidation. When those tag invalidations happen, trying
      // to log to the database, as in the case of dblog, will throw an
      // exception, since it's DB schema is gone. In that rare case, we catch
      // and ignore the exception here.
    }

    if ($this->isEnabled()) {
      $request = $this->requestStack->getCurrentRequest();
      // We don't use Monolog's NR handler because it just sets attributes on an existing event. See \Monolog\Handler\NewRelicHandler.
      // We can't record just one event because https://discuss.newrelic.com/t/how-to-send-multiple-items-in-a-custom-attribute/9280/5.
      foreach ($tags as $tag) {
        // Only invalidate tags once per request unless they are written again.
        if (isset($this->invalidatedTags[$tag])) {
          continue;
        }
        $this->invalidatedTags[$tag] = TRUE;

        $attributes = [
          'tag' => $tag,
          'uri' => $request->getBaseUrl() . $request->getPathInfo(),
          // Acquia uses this to identify a request. https://docs.acquia.com/acquia-cloud/develop/env-variable/
          // Its harmless for anyone else. Feel free to override and record your own request-id here.
          'request_id' => getenv('HTTP_X_REQUEST_ID'),
          // A Cloudflare trace header.
          'cf_ray' => $this->requestStack->getCurrentRequest()->headers->get('CF-RAY'),
          'username' => $this->currentUser->getAccountName(),
        ];
        $this->record($attributes);
      }
    }
  }

  /**
   * Use a cache_metrics.services.yml parameter if you have the NR extension but dont want this logging.
   *
   * @return bool
   */
  public function isEnabled() {
    return $this->isEnabled && function_exists('newrelic_record_custom_event');
  }

  /**
   * Record the invalidation.
   *
   * @param array $attributes
   */
  protected function record(array $attributes) {
    newrelic_record_custom_event('InvalidateTag', $attributes);
  }

}
