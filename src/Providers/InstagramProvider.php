<?php

namespace Laravel\SocialCredentials\Providers;

use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

/**
 * Class Instagram Provider
 *
 * @package     Laravel\SocialCredentials\Providers
 * @author      Oanh Nguyen <oanhnn.bk@gmail.com>
 * @license     The MIT license
 */
class InstagramProvider extends AbstractProvider
{
    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['basic'];

    /**
     * Get the authentication URL for the provider.
     *
     * @param  string $state
     * @return string
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://api.instagram.com/oauth/authorize', $state);
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl()
    {
        return 'https://api.instagram.com/oauth/access_token';
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param  string $token
     * @return array
     */
    protected function getUserByToken($token)
    {
        $endpoint = '/users/self';
        $query = [
            'access_token' => $token,
        ];
        $signature = $this->generateSignature($endpoint, $query);
        $query['sig'] = $signature;

        $response = $this->getHttpClient()->get(
            'https://api.instagram.com/v1/users/self',
            [
                'query'   => $query,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]
        );

        return json_decode($response->getBody()->getContents(), true)['data'];
    }

    /**
     * Map the raw user array to a Socialite User instance.
     *
     * @param  array $user
     * @return \Laravel\Socialite\Two\User
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id'       => $user['id'],
            'nickname' => $user['username'],
            'name'     => $user['full_name'],
            'email'    => null,
            'avatar'   => $user['profile_picture'],
        ]);
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param  string $code
     * @return array
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * Allows compatibility for signed API requests.
     *
     * @param  string @endpoint
     * @param  array $params
     * @return string
     */
    protected function generateSignature($endpoint, array $params)
    {
        $sig = $endpoint;
        ksort($params);
        foreach ($params as $key => $val) {
            $sig .= "|$key=$val";
        }
        $signingKey = $this->clientSecret;

        return hash_hmac('sha256', $sig, $signingKey, false);
    }

    /**
     * Get the access token from the token response body.
     *
     * @param  array $body
     * @return string
     */
    protected function parseAccessToken(array $body)
    {
        return Arr::get($body, 'access_token');
    }

    /**
     * Get the refresh token from the token response body.
     *
     * @param  array $body
     * @return string
     */
    protected function parseRefreshToken(array $body)
    {
        return Arr::get($body, 'refresh_token');
    }

    /**
     * Get the expires in from the token response body.
     *
     * @param  array $body
     * @return string
     */
    protected function parseExpiresIn(array $body)
    {
        return Arr::get($body, 'expires_in');
    }
}
