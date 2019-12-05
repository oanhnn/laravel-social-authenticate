<?php

namespace Laravel\SocialCredentials\Providers;

use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User;

/**
 * Class LINE Provider
 *
 * @package     Laravel\SocialCredentials\Providers
 * @author      Oanh Nguyen <oanhnn.bk@gmail.com>
 * @license     The MIT license
 */
class LineProvider extends AbstractProvider
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
    protected $scopes = [
        'openid',
        'profile',
        'email',
    ];

    /**
     * Get the User instance for the authenticated user.
     *
     * @return \Laravel\Socialite\Contracts\User
     * @throws \Laravel\Socialite\Two\InvalidStateException
     */
    public function user()
    {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException();
        }

        $response = $this->getAccessTokenResponse($this->getCode());

        if ($jwt = $response['id_token'] ?? null) {
            list($headb64, $bodyb64, $cryptob64) = explode('.', $jwt);
            $user = $this->mapUserToObject(json_decode(base64_decode(strtr($bodyb64, '-_', '+/')), true));
        } else {
            $user = $this->mapUserToObject($this->getUserByToken(
                $token = $this->parseAccessToken($response)
            ));
        }

        return $user->setToken($this->parseAccessToken($response))
            ->setRefreshToken($this->parseRefreshToken($response))
            ->setExpiresIn($this->parseExpiresIn($response));
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param  string $state
     * @return string
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://access.line.me/oauth2/v2.1/authorize', $state);
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl()
    {
        return 'https://api.line.me/oauth2/v2.1/token';
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param  string $token
     * @return array
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get(
            'https://api.line.me/v2/profile',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]
        );
        return json_decode($response->getBody()->getContents(), true);
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
            'id'       => $user['userId'] ?? $user['sub'] ?? null,
            'nickname' => null,
            'name'     => $user['displayName'] ?? $user['name'] ?? null,
            'avatar'   => $user['pictureUrl'] ?? $user['picture'] ?? null,
            'email'    => $user['email'] ?? null,
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
