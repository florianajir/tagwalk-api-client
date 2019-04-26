<?php
/**
 * PHP version 7
 *
 * LICENSE: This source file is subject to copyright
 *
 * @author      Florian Ajir <florian@tag-walk.com>
 * @copyright   2016-2019 TAGWALK
 * @license     proprietary
 */

namespace Tagwalk\ApiClientBundle\Manager;

use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;
use Tagwalk\ApiClientBundle\Provider\ApiProvider;

class TrendManager
{
    const DEFAULT_STATUS = 'enabled';
    const DEFAULT_SORT = 'created_at:desc';

    /**
     * @var ApiProvider
     */
    private $apiProvider;

    /**
     * @var AnalyticsManager
     */
    private $analytics;

    /**
     * @var FilesystemAdapter
     */
    private $cache;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var int
     */
    private $lastCount;

    /**
     * @param ApiProvider $apiProvider
     * @param AnalyticsManager $analytics
     * @param int $cacheTTL
     * @param string|null $cacheDirectory
     */
    public function __construct(ApiProvider $apiProvider, AnalyticsManager $analytics, int $cacheTTL = 600, string $cacheDirectory = null)
    {
        $this->apiProvider = $apiProvider;
        $this->analytics = $analytics;
        $this->cache = new FilesystemAdapter('medias', $cacheTTL, $cacheDirectory);
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param null|string $type
     * @param null|string $season
     * @param null|string $city
     * @param int $from
     * @param int $size
     * @param string $sort
     * @param string $status
     * @return array
     */
    public function list(
        ?string $type = null,
        ?string $season = null,
        ?string $city = null,
        $from = 0,
        $size = 10,
        $sort = self::DEFAULT_SORT,
        $status = self::DEFAULT_STATUS
    ) {
        $query = array_filter(compact('type', 'season', 'city', 'from', 'size', 'sort', 'status'));
        $cacheKey = 'list.' . md5(serialize($query));
        $countCacheKey = "count.$cacheKey";
        $this->lastCount = $this->cache->getItem($countCacheKey)->get();

        if ($this->cache->hasItem($cacheKey)) {
            $analytics = array_merge($query, ['count' => $this->lastCount]);
            $this->analytics->page('trend_list', $analytics);
        }

        return $this->cache->get($cacheKey, function () use ($query, $countCacheKey) {
            $data = [];
            $apiResponse = $this->apiProvider->request('GET', '/api/trends', [RequestOptions::QUERY => $query, RequestOptions::HTTP_ERRORS => false]);
            if ($apiResponse->getStatusCode() === Response::HTTP_OK) {
                $data = json_decode($apiResponse->getBody(), true);
                $this->lastCount = (int)$apiResponse->getHeaderLine('X-Total-Count');
                $countCacheItem = $this->cache->getItem($countCacheKey)->set($this->lastCount);
                $this->cache->save($countCacheItem);
            } else {
                $this->logger->error($apiResponse->getBody()->getContents());
                $this->lastCount = 0;
            }

            return $data;
        });
    }

    /**
     * @param string $slug
     * @return array
     */
    public function get(string $slug)
    {
        if ($this->cache->hasItem($slug)) {
            $this->analytics->page('trend_show', compact('slug'));
        }

        return $this->cache->get($slug, function () use ($slug) {
            $data = null;
            $apiResponse = $this->apiProvider->request('GET', '/api/trends/' . $slug, [RequestOptions::HTTP_ERRORS => false]);
            if ($apiResponse->getStatusCode() === Response::HTTP_OK) {
                $data = json_decode($apiResponse->getBody(), true);
            } elseif ($apiResponse->getStatusCode() !== Response::HTTP_NOT_FOUND) {
                $this->logger->error($apiResponse->getBody()->getContents());
            }

            return $data;
        });
    }

    /**
     * @param string $type
     * @param string $season
     * @param null|string $city
     * @return array
     */
    public function findBy(string $type, string $season, ?string $city = null): array
    {
        $query = array_filter(compact('type', 'season', 'city'));
        $cacheKey = 'findBy.' . md5(serialize($query));
        if ($this->cache->hasItem($cacheKey)) {
            $this->analytics->page('trend_season_list', $query);
        }

        return $this->cache->get($cacheKey, function () use ($type, $season, $city) {
            $data = [];
            $query = array_filter(compact('city'));
            $apiResponse = $this->apiProvider->request('GET', "/api/trends/$type/$season", [
                'query' => $query,
                RequestOptions::HTTP_ERRORS => false
            ]);
            if ($apiResponse->getStatusCode() === Response::HTTP_OK) {
                $data = json_decode($apiResponse->getBody(), true);
            } else {
                $this->logger->error($apiResponse->getBody()->getContents());
            }

            return $data;
        });
    }
}
