<?php
/**
 * PHP version 7.
 *
 * LICENSE: This source file is subject to copyright
 *
 * @author      Florian Ajir <florian@tag-walk.com>
 * @author      Steve Valette <steve@tag-walk.com>
 * @copyright   2016-2019 TAGWALK
 * @license     proprietary
 */

namespace Tagwalk\ApiClientBundle\Manager;

use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Tagwalk\ApiClientBundle\Model\User;
use Tagwalk\ApiClientBundle\Provider\ApiProvider;

class UserManager
{
    /**
     * @var ApiProvider
     */
    private $apiProvider;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @param ApiProvider         $apiProvider
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ApiProvider $apiProvider,
        SerializerInterface $serializer
    ) {
        $this->apiProvider = $apiProvider;
        $this->serializer = $serializer;
    }

    /**
     * @param string $email
     *
     * @return User|null
     */
    public function get(string $email): ?User
    {
        $user = null;
        $apiResponse = $this->apiProvider->request('GET', '/api/users/' . $email,
            [RequestOptions::HTTP_ERRORS => false]);
        if ($apiResponse->getStatusCode() === Response::HTTP_OK) {
            $user = $this->deserialize($apiResponse);
        }

        return $user;
    }

    /**
     * @param ResponseInterface $response
     *
     * @return User
     */
    private function deserialize($response): User
    {
        /** @var User $user */
        $user = $this->serializer->deserialize(
            (string)$response->getBody(),
            User::class,
            JsonEncoder::FORMAT
        );

        return $user;
    }

    /**
     * @param User $user
     *
     * @return User|null
     */
    public function create(User $user): ?User
    {
        $data = $this->serializer->normalize($user, null, ['write' => true]);
        $apiResponse = $this->apiProvider->request('POST', '/api/users/register', [
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::JSON        => $data,
        ]);
        $created = null;
        if ($apiResponse->getStatusCode() === Response::HTTP_CREATED) {
            $created = $this->deserialize($apiResponse);
        } elseif ($apiResponse->getStatusCode() === Response::HTTP_CONFLICT) {
            throw new InvalidArgumentException('User already exists');
        }

        return $created;
    }

    /**
     * @param string      $email
     * @param User        $user
     * @param string|null $appContext
     *
     * @return User|null
     */
    public function update(string $email, User $user, ?string $appContext = null): ?User
    {
        $data = $this->serializer->normalize($user, null, ['write' => true]);
        $data = array_filter($data, static function ($v) {
            return $v !== null;
        });

        return $this->doUpdate($data, $email, $appContext);
    }

    /**
     * @param string      $email
     * @param string      $property
     * @param mixed       $value
     * @param string|null $appContext
     *
     * @return User|null
     */
    public function patch(string $email, string $property, $value, ?string $appContext = null): ?User
    {
        $data = [$property => $value];

        return $this->doUpdate($data, $email, $appContext);
    }

    private function doUpdate($data, $email, $appContext): ?User
    {
        $params = array_filter([
            'email'               => $email,
            'application_context' => $appContext,
        ]);
        $apiResponse = $this->apiProvider->request('PATCH', '/api/users', [
            RequestOptions::QUERY       => $params,
            RequestOptions::JSON        => $data,
            RequestOptions::HTTP_ERRORS => false,
        ]);
        $updated = null;
        if ($apiResponse->getStatusCode() === Response::HTTP_OK) {
            $updated = $this->deserialize($apiResponse);
        }

        return $updated;
    }

    /**
     * @param string $property
     * @param string $value
     *
     * @return User|null
     */
    public function findBy(string $property, string $value): ?User
    {
        $data = null;
        $apiResponse = $this->apiProvider->request('GET', '/api/users/find', [
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::QUERY       => [
                'key'   => $property,
                'value' => $value,
            ],
        ]);
        if ($apiResponse->getStatusCode() === Response::HTTP_OK) {
            $data = $this->deserialize($apiResponse);
        }

        return $data;
    }

    /**
     * @param string $email
     *
     * @return bool
     */
    public function delete(string $email): bool
    {
        $apiResponse = $this->apiProvider->request('DELETE', sprintf('/api/users/%s', $email), [
            RequestOptions::HTTP_ERRORS => false,
        ]);

        return $apiResponse->getStatusCode() === Response::HTTP_NO_CONTENT;
    }
}
