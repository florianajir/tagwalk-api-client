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

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Tagwalk\ApiClientBundle\Model\Designer;
use Tagwalk\ApiClientBundle\Provider\ApiProvider;

class DesignerManager
{
    const DEFAULT_STATUS = 'enabled';
    const DEFAULT_SORT = 'name:asc';

    /**
     * @var ApiProvider
     */
    private $apiProvider;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var FilesystemAdapter
     */
    private $cache;

    /**
     * @param ApiProvider $apiProvider
     * @param SerializerInterface $serializer
     */
    public function __construct(ApiProvider $apiProvider, SerializerInterface $serializer)
    {
        $this->apiProvider = $apiProvider;
        $this->serializer = $serializer;
        $this->cache = new FilesystemAdapter('designers');
    }

    /**
     * @param string $slug
     * @param string $locale
     * @return Designer|null
     */
    public function get(string $slug, $locale = null): ?Designer
    {
        $designer = null;
        $key = isset($locale) ? "{$locale}.{$slug}" : $slug;
        $cacheItem = $this->cache->getItem($key);
        if ($cacheItem->isHit()) {
            $designer = $cacheItem->get();
        } else {
            $query = isset($locale) ? ['language' => $locale] : [];
            $apiResponse = $this->apiProvider->request('GET', '/api/designers/' . $slug, ['http_errors' => false, 'query' => $query]);
            if ($apiResponse->getStatusCode() === Response::HTTP_OK) {
                $designer = $this->serializer->deserialize($apiResponse->getBody()->getContents(), Designer::class, 'json');
                $cacheItem->set($designer);
                $cacheItem->expiresAfter(86400);
                $this->cache->save($cacheItem);
            }
        }

        return $designer;
    }

    /**
     * @param string|null $language
     * @param int $from
     * @param int $size
     * @param string $sort
     * @param string $status
     * @param bool $denormalize
     * @return array|Designer[]
     */
    public function list(
        string $language = null,
        int $from = 0,
        int $size = 20,
        string $sort = self::DEFAULT_SORT,
        string $status = self::DEFAULT_STATUS,
        bool $denormalize = true
    ): array {
        $designers = [];
        $query = array_filter(compact('from', 'size', 'sort', 'status', 'language'));
        $key = md5(serialize(array_merge($query, ['denormalize' => $denormalize])));
        $cacheItem = $this->cache->getItem($key);
        if ($cacheItem->isHit()) {
            $designers = $cacheItem->get();
        } else {
            $apiResponse = $this->apiProvider->request('GET', '/api/designers', ['query' => $query, 'http_errors' => false]);
            if ($apiResponse->getStatusCode() === Response::HTTP_OK) {
                $data = json_decode($apiResponse->getBody()->getContents(), true);
                if ($denormalize) {
                    foreach ($data as $datum) {
                        $designers[] = $this->serializer->denormalize($datum, Designer::class);
                    }
                } else {
                    $designers = $data;
                }
                $cacheItem->set($designers);
                $cacheItem->expiresAfter(3600);
                $this->cache->save($cacheItem);
            }
        }

        return $designers;
    }

    /**
     * TODO implement count API endpoint
     *
     * @param string $status
     * @return int
     */
    public function count(string $status = self::DEFAULT_STATUS): int
    {
        $count = 0;
        $cacheItem = $this->cache->getItem('count');
        if ($cacheItem->isHit()) {
            $count = $cacheItem->get();
        } else {
            $apiResponse = $this->apiProvider->request('GET', '/api/designers', ['query' => ['status' => $status, 'size' => 1], 'http_errors' => false]);
            if ($apiResponse->getStatusCode() === Response::HTTP_OK) {
                $count = (int)$apiResponse->getHeaderLine('X-Total-Count');
                $cacheItem->set($count);
                $cacheItem->expiresAfter(3600);
                $this->cache->save($cacheItem);
            }
        }

        return $count;
    }

    /**
     * @param string $prefix
     * @param string|null $language
     * @return array
     */
    public function suggest(
        string $prefix,
        string $language = null
    ): array {
        $designers = [];
        $query = array_filter(compact('prefix', 'language'));
        $key = md5(serialize($query));
        $cacheItem = $this->cache->getItem($key);
        if ($cacheItem->isHit()) {
            $designers = $cacheItem->get();
        } else {
            $apiResponse = $this->apiProvider->request('GET', '/api/designers/suggestions', ['query' => $query, 'http_errors' => false]);
            if ($apiResponse->getStatusCode() === Response::HTTP_OK) {
                $designers = json_decode($apiResponse->getBody()->getContents(), true);
                $cacheItem->set($designers);
                $cacheItem->expiresAfter(3600);
                $this->cache->save($cacheItem);
            }
        }

        return $designers;
    }

    /**
     * @param null|string $type
     * @param null|string $season
     * @param null|string $city
     * @param null|string $tags
     * @param null|string $models
     * @param string|null $language
     * @return Designer[]
     */
    public function listFilters(
        ?string $type,
        ?string $season,
        ?string $city,
        ?string $tags,
        ?string $models,
        ?string $language = null
    ): array {
        $designers = [];
        $query = array_filter(compact('type', 'season', 'city', 'tags', 'models', 'language'));
        $key = md5(serialize($query));
        $cacheItem = $this->cache->getItem($key);
        if ($cacheItem->isHit()) {
            $designers = $cacheItem->get();
        } else {
            $apiResponse = $this->apiProvider->request('GET', '/api/designers/filter', ['query' => $query, 'http_errors' => false]);
            if ($apiResponse->getStatusCode() === Response::HTTP_OK) {
                $data = json_decode($apiResponse->getBody()->getContents(), true);
                foreach ($data as $datum) {
                    $designers[] = $this->serializer->denormalize($datum, Designer::class);
                }
                $cacheItem->set($designers);
                $cacheItem->expiresAfter(3600);
                $this->cache->save($cacheItem);
            }
        }

        return $designers;
    }
}
