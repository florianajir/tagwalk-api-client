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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Tagwalk\ApiClientBundle\Model\Individual;
use Tagwalk\ApiClientBundle\Provider\ApiProvider;

/**
 * TODO implement cache + return objects
 */
class ModelManager extends IndividualManager
{
    /**
     * @param ApiProvider $apiProvider
     * @param Serializer $serializer
     */
    public function __construct(ApiProvider $apiProvider, Serializer $serializer)
    {
        parent::__construct($apiProvider, $serializer);
    }

    /**
     * @param string $type
     * @param string $season
     * @param string $city
     * @param int $length
     *
     * @return array
     */
    public function whoWalkedTheMost($type = null, $season = null, $city = null, $length = 10)
    {
        $query = array_filter(compact('type', 'season', 'city', 'length'));
        $apiResponse = $this->apiProvider->request('GET', '/api/models/who-walked-the-most', ['query' => $query, 'http_errors' => false]);
        $data = json_decode($apiResponse->getBody(), true);

        return $data;
    }

    /**
     * @param int $size
     * @param int $page
     * @param array $params
     * @return array
     */
    public function listMediasModels(int $size, int $page, array $params = []): array
    {
        $apiResponse = $this->apiProvider->request('GET', '/api/models', [
            'query' => array_merge($params, [
                'size' => $size,
                'page' => $page
            ]),
            'http_errors' => false
        ]);
        $data = json_decode($apiResponse->getBody(), true);

        return $data;
    }

    /**
     * @param int $size
     * @param int $page
     * @param array $params
     * @return int
     */
    public function countListMediasModels(int $size, int $page, array $params = []): int
    {
        $apiResponse = $this->apiProvider->request('GET', '/api/models', [
            'query' => array_merge($params, [
                'size' => $size,
                'page' => $page
            ]),
            'http_errors' => false
        ]);

        return (int)$apiResponse->getHeaderLine('X-Total-Count');

    }

    /**
     * @param string $slug
     * @param array $params
     * @return mixed
     */
    public function listMediasModel(string $slug, array $params)
    {
        $apiResponse = $this->apiProvider->request('GET', '/api/individuals/' . $slug . '/medias', ['query' => $params, 'http_errors' => false]);
        $data = json_decode($apiResponse->getBody(), true);

        return $data;
    }

    /**
     * @param string $slug
     * @param array $params
     *
     * @return int
     */
    public function countListMediasModel(string $slug, array $params): int
    {
        $apiResponse = $this->apiProvider->request('GET', '/api/individuals/' . $slug . '/medias', ['query' => $params, 'http_errors' => false]);

        return (int)$apiResponse->getHeaderLine('X-Total-Count');
    }

    /**
     * @return Individual[]
     */
    public function getNewFaces(): array
    {
        $apiResponse = $this->apiProvider->request('GET', '/api/models/new-faces', ['http_errors' => false]);
        $data = json_decode($apiResponse->getBody(), true);
        $list = [];
        if (!empty($data)) {
            foreach ($data as $datum) {
                $list[] = $this->serializer->denormalize($datum, Individual::class);
            }
        }

        return $data;
    }

    /**
     * @return int
     */
    public function countNewFaces(): int
    {
        $apiResponse = $this->apiProvider->request('GET', '/api/models/new-faces', ['http_errors' => false]);

        return (int)$apiResponse->getHeaderLine('X-Total-Count');
    }

    /**
     * @param null|string $type
     * @param null|string $season
     * @param null|string $city
     * @param null|string $designer
     * @param null|string $tags
     * @param string|null $language
     * @return Individual[]
     */
    public function listFilters(
        ?string $type,
        ?string $season,
        ?string $city,
        ?string $designer,
        ?string $tags,
        ?string $language = null
    ): array {
        $models = [];
        $query = array_filter(compact('type', 'season', 'city', 'designer', 'tags', 'language'));
        $key = md5(serialize($query));
        $cacheItem = $this->cache->getItem($key);
        if ($cacheItem->isHit()) {
            $models = $cacheItem->get();
        } else {
            $apiResponse = $this->apiProvider->request('GET', '/api/models/filter', ['query' => $query, 'http_errors' => false]);
            if ($apiResponse->getStatusCode() === Response::HTTP_OK) {
                $data = json_decode($apiResponse->getBody()->getContents(), true);
                foreach ($data as $datum) {
                    $models[] = $this->serializer->denormalize($datum, Individual::class);
                }
                $cacheItem->set($models);
                $cacheItem->expiresAfter(3600);
                $this->cache->save($cacheItem);
            }
        }

        return $models;
    }
}
