<?php

namespace Laravel\SocialCredentials;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\RedirectsUsers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\SocialCredentials\Models\SocialCredential;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;

/**
 * Trait authenticate with Socialite
 *
 * @package     Laravel\SocialCredentials
 * @author      Oanh Nguyen <oanhnn.bk@gmail.com>
 * @license     The MIT license
 */
trait AuthWithSocialite
{
    use RedirectsUsers;

    /**
     * Find or create user from socialite user info
     * It should fire event Registered when create new user
     *
     * @param  \Laravel\Socialite\AbstractUser $socialiteUser
     * @param  string $provider
     * @return \Illuminate\Contracts\Auth\Authenticatable
     */
    abstract protected function findOrCreateUser(AbstractUser $socialiteUser, string $provider): Authenticatable;

    /**
     * Redirects to Socialite Provider authoize page
     *
     * @param  string $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToProvider($provider)
    {
        abort_unless($this->allowedProvider($provider), Response::HTTP_BAD_REQUEST);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Login with Socialite
     *
     * @param  \Illuminate\Http\Request $request
     * @param  string $provider
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handleProviderCallback(Request $request, $provider)
    {
        abort_unless($this->allowedProvider($provider), Response::HTTP_BAD_REQUEST);

        $socialiteUser = Socialite::driver($provider)->user();

        $user = $this->getUser($socialiteUser, $provider);

        $user->load('socialCredentials');

        auth()->login($user);

        return $this->authenticated($request, $user) ?: redirect()->intended($this->redirectPath());
    }

    /**
     * Get user model from socialite user info
     *
     * @param  \Laravel\Socialite\AbstractUser $socialiteUser
     * @param  string $provider
     * @return \Illuminate\Contracts\Auth\Authenticatable
     */
    protected function getUser(AbstractUser $socialiteUser, string $provider): Authenticatable
    {
        return $this->createCredentials($socialiteUser, $provider)->owner;
    }

    /**
     * Create social credential model
     *
     * @param  \Laravel\Socialite\AbstractUser $socialiteUser
     * @param  string $provider
     * @return \Laravel\SocialCredentials\Models\SocialCredential
     * @throws \Throwable
     */
    protected function createCredentials(AbstractUser $socialiteUser, string $provider): SocialCredential
    {
        $socialiteCredential = (new SocialCredential())
            ->with('owner')
            ->firstOrNew([
                'provider_id' => $socialiteUser->getId(),
                'provider_name' => $provider,
            ])
            ->fill([
                'access_token' => $socialiteUser->token,
                'avatar' => $socialiteUser->getAvatar(),
                'email' => $socialiteUser->getEmail(),
                'expires_at' => now()->addSeconds($socialiteUser->expiresIn),
                'name' => $socialiteUser->getName(),
                'nickname' => $socialiteUser->getNickname(),
                'provider_id' => $socialiteUser->getId(),
                'provider_name' => $provider,
                'refresh_token' => $socialiteUser->refreshToken,
            ]);

        if (!$socialiteCredential->exists) {
            $user = $this->findOrCreateUser($socialiteUser, $provider);
            $socialiteCredential->owner()->associate($user);
        }

        $socialiteCredential->saveOrFail();

        return $socialiteCredential;
    }

    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        //
    }

    /**
     * Check Socialite provider is allowed
     *
     * @param  string $provider
     * @return bool
     */
    protected function allowedProvider(string $provider): bool
    {
        return config()->has('services.' . $provider);
    }

    /**
     * Build service provider object
     *
     * @param  string $provider
     * @return \Laravel\Socialite\Contracts\Provider
     */
    protected function buildProvider(string $provider)
    {
        $config = config()->get("services.{$provider}");

        /** @var \Laravel\Socialite\Contracts\Provider $providerObj */
        $providerObj = Socialite::driver($provider);
        if (isset($config['scopes']) && method_exists($providerObj, 'setScopes')) {
            $providerObj->setScopes($config['scopes']);
        }

        return $providerObj;
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard();
    }
}
