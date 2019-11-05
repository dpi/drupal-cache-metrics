Introduction
-------------

  * Logs all cache tag invalidations.
  * Sends cache tag invalidations to New Relic as Custom Events. Visualize and Analyze via New Relic Insights.
  * Sends cache hits/misses to New Relic as Custom Events.

See the images that are attached to https://www.drupal.org/project/cache_metrics for example reports.

Per environment customization
-------------
In general, sites want this module enabled in Production and not on development 
environments. You can do that in Drupal 8.8+ with https://www.drupal.org/node/3079028.

Container parameters
--------------
This module is configured by container parameters (i.e. in a services,yml file). 
You can customize cache_metrics.new_relic.invalidations and cache_metrics.bins.blacklist. 
A blacklist value of '*' effectively disables cache hit/miss logging.

New Relic Insights
--------------
The default reporting tool is New Relic Insights. Some example NRQL 

```
SELECT 
  ROUND((SUM(miss)/SUM(miss+hit))*100) AS 'Miss %', 
  SUM(miss) AS 'Misses'
FROM CacheGet 
FACET bin
```

Using an alternative analytics provider
----------------
In order to use something other than New Relic custom events, 

1. Override the `cache_metrics.cache_factory` service. Change the `isEnabled()`
method as needed, and also change the `get()` method to instantiate a different 
CacheBackendWrapper class. That class can extend this module's 
CacheBackendWrapper and override the `record()` method.
1. Override the `cache_metrics.invalidator service` and \Drupal\cache_metrics\CacheMetricsCacheTagsInvalidator::invalidateTags
method to log elsewhere. Also note \Drupal\cache_metrics\CacheMetricsCacheTagsInvalidator::isEnabled method. 
 
Reducing voluminous data logging
---------
The hit/miss cache logging from this module can generate many thousands of events 
per minute on a popular site. Since the New Relic daemon buffers these events 
and send them every minute, this does not affect page performance. If you exceed NR
limits, [the daemon will automatically start sampling](https://docs.newrelic.com/docs/agents/manage-apm-agents/agent-data/new-relic-events-limits-sampling).

This module defaults to omitting cache gets to the `config` bin. Additional bins may 
be blacklisted via a service parameter.     
