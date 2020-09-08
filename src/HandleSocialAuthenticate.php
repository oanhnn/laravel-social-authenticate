<?php

namespace Laravel\SocialAuthenticate;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\SocialAuthenticate\Models\SocialCredential;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;

/**
 * Trait HandleSocialAuthenticate for handler controller
 *
 * @package     Laravel\SocialAuthenticate
 * @author      Oanh Nguyen <oanhnn.bk@gmail.com>
 * @license     The MIT license
 */
trait HandleSocialAuthenticate
{
    /**
     * Redirects to Socialite Provider authoize page
     *
     * @param  \Illuminate\Http\Request $request
     * @param  string $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect(Request $request, string $provider)
    {
        if (!$this->isAllowedProvider($provider)) {
            return $this->providerRejected($request, $provider);
        }

        return $this->getSocialiteRedirect($request, $provider);
    }

    /**
     * Hanlde callback request from Socialite Provider authoize page
     *
     * @param  \Illuminate\Http\Request $request
     * @param  string $provider
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     * @throws \Throwable
     */
    public function callback(Request $request, string $provider)
    {
        if (!$this->isAllowedProvider($provider)) {
            return $this->providerRejected($request, $provider);
        }

        $socialUser = $this->getSocialiteUser($request, $provider);

        // If the user tried to link the account, handle different logic.
        if ($this->isLinkCallbackRequest($request, $provider)) {
            return $this->linkCallback($request, $provider, $socialUser);
        }

        // If the Social is attached to any authenticatable model,
        // then jump off and login.
        if ($user = $this->findUserBySocialiteUser($request, $provider, $socialUser)) {
            $socialCredential = $this->updateSocialCredential(
                $user->getSocialCredential($provider),
                $request,
                $socialUser
            );

            $this->authenticateWith(
                $request,
                $user,
                $socialCredential,
                $socialUser
            );

            return $this->redirectAfterAuthenticated($request, $user, $socialCredential);
        }

        // Otherwise, create a new Authenticatable model
        // and attach a Social instance to it.
        if ($this->emailAlreadyRegistered($request, $provider, $socialUser)) {
            return $this->duplicateEmail($request, $provider, $socialUser);
        }

        $user = $this->registerWith($request, $provider, $socialUser);

        $socialCredential = $this->updateSocialCredential(
            $user->socialCredentials()->make([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
            ]),
            $request,
            $socialUser
        );

        return $this->redirectAfterRegistered($request, $user, $socialCredential, $socialUser);
    }

    /**
     * Check Socialite provider is allowed.
     * You should override this method
     *
     * @param  string $provider
     * @return bool
     */
    protected function isAllowedProvider(string $provider): bool
    {
        return \config()->has("services.{$provider}");
    }

    /**
     * Provider rejected handle
     *
     * @param  \Illuminate\Http\Request $request
     * @param  string $provider
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    protected function providerRejected(Request $request, string $provider)
    {
        $provider = \ucfirst($provider);
        \session()->flash('social', "The authentication with {$provider} failed!");

        return \redirect()->back();
    }

    /**
     * Get the Socialite direct instance that will redirect
     * the user to the right provider OAuth page.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $provider
     * @return mixed
     */
    protected function getSocialiteRedirect(Request $request, string $provider)
    {
        return $this->getSocialiteProvider($provider)->redirect();
    }

    /**
     * Get the Socialite User instance that will be
     * given after the OAuth authorization passes.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $provider
     * @return \Laravel\Socialite\AbstractUser
     */
    protected function getSocialiteUser(Request $request, string $provider): AbstractUser
    {
        return $this->getSocialiteProvider($provider)->user();
    }

    /**
     * Get Socialite provider object
     *
     * @param  string $provider
     * @return \Laravel\Socialite\Contracts\Provider
     */
    protected function getSocialiteProvider(string $provider): Provider
    {
        $config = \config()->get("services.{$provider}");

        /** @var \Laravel\Socialite\Contracts\Provider $providerObj */
        $providerObj = Socialite::driver($provider);
        if (isset($config['scopes']) && \method_exists($providerObj, 'setScopes')) {
            $providerObj->setScopes($config['scopes']);
        }

        return $providerObj;
    }

    /**
     * Verify request is link callback
     *
     * @param \Illuminate\Http\Request $request
     * @param string $provider
     * @return bool
     */
    protected function isLinkCallbackRequest(Request $request, string $provider): bool
    {
        if (\method_exists($this, 'linkCallback') && $request->user()) {
            return true;
        }

        return false;
    }

    /**
     * Find user by social user detail
     *
     * @param  \Illuminate\Http\Request $request
     * @param  string $provider
     * @param  \Laravel\Socialite\AbstractUser $socialUser
     * @return \Laravel\SocialAuthenticate\Contracts\Authenticatable|null
     */
    protected function findUserBySocialiteUser(
        Request $request,
        string $provider,
        AbstractUser $socialUser
    ): ?Authenticatable {
        $socialCredential = SocialCredential::query()
            ->with('owner')
            ->where('provider_name', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        return $socialCredential ? $socialCredential->owner : null;
    }

    /**
     * Update social credential
     *
     * @param  \Laravel\SocialAuthenticate\Models\SocialCredential $socialCredential
     * @param  \Illuminate\Http\Request $request
     * @param  \Laravel\Socialite\AbstractUser $socialUser
     * @return SocialCredential
     */
    protected function updateSocialCredential(
        SocialCredential $socialCredential,
        Request $request,
        AbstractUser $socialUser
    ): SocialCredential {
        $socialCredential->update([
            'name' => $socialUser->getName(),
            'nickname' => $socialUser->getNickname(),
            'email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getName(),
            'access_token' => $socialUser->token,
            'expires_at' => isset($socialUser->expiresIn) ? \now()->addSeconds($socialUser->expiresIn) : null,
            'refresh_token' => $socialUser->refreshToken ?? $socialUser->tokenSecret ?? null,
            'raw' => $socialUser->getRaw(),
        ]);

        return $socialCredential;
    }

    /**
     * Send the response when duplicate email.
     *
     * @param  \Illuminate\Http\Request                              $request
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @param  \Laravel\Socialite\AbstractUser                       $socialUser
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    protected function emailAlreadyRegistered(Request $request, string $provider, AbstractUser $socialUser): bool
    {
        // TODO
        return false;
    }

    /**
     * Send the response when duplicate email.
     *
     * @param  \Illuminate\Http\Request                              $request
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @param  \Laravel\Socialite\AbstractUser                       $socialUser
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    protected function duplicateEmail(Request $request, string $provider, AbstractUser $socialUser)
    {
        \session()->flash('social', "The authentication with {$provider} failed!");

        return \redirect()->back();
    }

    /**
     * Authenticate with user model
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  \Laravel\SocialAuthenticate\Models\SocialCredential $socialCredential
     * @param  \Laravel\Socialite\AbstractUser $socialUser
     * @return void
     */
    protected function authenticateWith(
        Request $request,
        Authenticatable $user,
        SocialCredential $socialCredential,
        AbstractUser $socialUser
    ) {
        $this->guard()->login($user);
    }

    /**
     * Register new user with social user detail
     *
     * @param  \Illuminate\Http\Request $request
     * @param  string $provider
     * @param  \Laravel\Socialite\AbstractUser $socialUser
     * @return \Laravel\SocialAuthenticate\Contracts\Authenticatable
     */
    abstract protected function registerWith(
        Request $request,
        string $provider,
        AbstractUser $socialUser
    ): Authenticatable;

    /**
     * Send the response after the user was authenticated.
     *
     * @param  \Illuminate\Http\Request                              $request
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @param  \Laravel\SocialAuthenticate\Models\SocialCredential   $socialCredential
     * @param  \Laravel\Socialite\AbstractUser                       $socialUser
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    protected function redirectAfterRegistered(
        Request $request,
        Authenticatable $user,
        SocialCredential $socialCredential,
        AbstractUser $socialUser
    ) {
        return $this->registered(
            $request,
            $user,
            $socialCredential
        ) ?? $this->redirectAfterAuthenticated($request, $user, $socialCredential);
    }

    /**
     * Send the response after the user was authenticated.
     *
     * @param  \Illuminate\Http\Request                              $request
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @param  \Laravel\SocialAuthenticate\Models\SocialCredential   $socialCredential
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    protected function redirectAfterAuthenticated(
        Request $request,
        Authenticatable $user,
        SocialCredential $socialCredential
    ) {
        return $this->authenticated(
            $request,
            $user,
            $socialCredential
        ) ?? \redirect()->intended(\route('home'));
    }

    /**
     * Send the response after the user was authenticate failed.
     *
     * @param  \Illuminate\Http\Request        $request
     * @param  \Laravel\Socialite\AbstractUser $socialUser
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    protected function redirectAfterAuthenticateFailed(
        Request $request,
        string $provider,
        AbstractUser $socialUser
    ) {
        $provider = \ucfirst($provider);
        \session()->flash('social', "The authentication with {$provider} failed!");

        return \redirect()->back();
    }

    /**
     * Handle the callback after the registration process.
     *
     * @param  \Illuminate\Http\Request                              $request
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @param  \Laravel\SocialAuthenticate\Models\SocialCredential   $socialCredential
     * @return mixed
     */
    protected function registered(
        Request $request,
        Authenticatable $user,
        SocialCredential $socialCredential
    ) {
        //
    }

    /**
     * Handle the callback after the login process.
     *
     * @param  \Illuminate\Http\Request                              $request
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @param  \Laravel\SocialAuthenticate\Models\SocialCredential   $socialCredential
     * @return mixed
     */
    protected function authenticated(
        Request $request,
        Authenticatable $user,
        SocialCredential $socialCredential
    ) {
        //
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
