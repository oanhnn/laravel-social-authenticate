<?php

namespace Laravel\SocialAuthenticate;

use Illuminate\Http\Request;
use Laravel\SocialAuthenticate\Contracts\Authenticatable;
use Laravel\SocialAuthenticate\Event\Unlinked;
use Laravel\SocialAuthenticate\Models\SocialCredential;
use Laravel\Socialite\AbstractUser;

/**
 * Trait HasSocialCredentials for user model
 *
 * @package     Laravel\SocialAuthenticate
 * @author      Oanh Nguyen <oanhnn.bk@gmail.com>
 * @license     The MIT license
 */
trait HandleSocialLink
{
    /**
     * Get the Socialite direct instance that will redirect
     * the user to the right provider OAuth page.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $provider
     * @return mixed
     */
    abstract protected function getSocialiteRedirect(Request $request, string $provider);

    /**
     * Get the Socialite User instance that will be
     * given after the OAuth authorization passes.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $provider
     * @return \Laravel\Socialite\AbstractUser
     */
    abstract protected function getSocialiteUser(Request $request, string $provider): AbstractUser;

    /**
     * Check Socialite provider is allowed.
     * You should override this method
     *
     * @param  string $provider
     * @return bool
     */
    abstract protected function isAllowedProvider(string $provider): bool;

    /**
     * Provider rejected handle
     *
     * @param  \Illuminate\Http\Request $request
     * @param  string $provider
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    abstract protected function providerRejected(Request $request, string $provider);

    /**
     * Update social credential
     *
     * @param  \Laravel\SocialAuthenticate\Models\SocialCredential $socialCredential
     * @param  \Illuminate\Http\Request $request
     * @param  \Laravel\Socialite\AbstractUser $socialUser
     * @return SocialCredential
     */
    abstract protected function updateSocialCredential(
        SocialCredential $socialCredential,
        Request $request,
        AbstractUser $socialUser
    ): SocialCredential;

    /**
     * Redirect to link a social account for the current authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function link(Request $request, string $provider)
    {
        if (!$this->isAllowedProvider($provider)) {
            return $this->providerRejected($request, $provider);
        }

        /** @var \Laravel\SocialAuthenticate\Contracts\Authenticatable $user */
        $user = $request->user();
        if ($user->hasSocialCredential($provider)) {
            return $this->providerAlreadyLinked($request, $provider, $user);
        }

        return $this->getSocialiteRedirect($request, $provider);
    }

    /**
     * Try to unlink a social account for the current authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function unlink(Request $request, string $provider)
    {
        if (!$this->isAllowedProvider($provider)) {
            return $this->providerRejected($request, $provider);
        }

        /** @var \Laravel\SocialAuthenticate\Contracts\Authenticatable $user */
        $user = $request->user();
        if ($socialCredential = $user->getSocialCredential($provider)) {
            $socialCredential->delete();
        }

        \event(new Unlinked($user, $provider));

        return $this->unlinked($request, $provider, $user) ?? $this->redirectAfterUnlink($request, $provider, $user);
    }

    /**
     * Handle the link callback to attach to an authenticatable ID.
     *
     * @param  \Illuminate\Http\Request                              $request
     * @param  string                                                $provider
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @param  \Laravel\Socialite\AbstractUser                       $socialUser
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function linkCallback(Request $request, string $provider, Authenticatable $user, $socialUser)
    {
        // Check if user has already a Social account with the provider.
        if ($user->hasSocialCredential($provider)) {
            return $this->providerAlreadyLinked($request, $provider, $user);
        }

        // Make sure that there are not two same authenticatables
        // that are linked to same social account.
        if ($this->getSocialById($request, $provider, $socialUser->getId())) {
            return $this->providerAlreadyLinkedByAnotherAuthenticatable($request, $provider, $user, $socialUser);
        }

        $socialCredential = $user->socialCredentials()->make([
            'provider_name' => $provider,
            'provider_id' => $socialUser->getId(),
        ]);

        /** @var \Laravel\SocialAuthenticate\Models\SocialCredential $socialCredential */
        $socialCredential = $this->updateSocialCredential(
            $socialCredential,
            $request,
            $socialUser
        );

        return $this->linked($request, $user, $socialCredential) ?? $this->redirectAfterLink(
            $request,
            $user,
            $socialCredential
        );
    }

    /**
     * Handle the callback when the user tries
     * to link a social account when it already has one, with the same provider.
     *
     * @param  \Illuminate\Http\Request                              $request
     * @param  string                                                $provider
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    protected function providerAlreadyLinked(Request $request, string $provider, Authenticatable $user)
    {
        $provider = \ucfirst($provider);
        \session()->flash('social', "You already have a {$provider} account linked.");

        return \redirect()->intended(\route('home'));
    }

    /**
     * Handle the callback when the user tries
     * to link a social account that is already existent.
     *
     * @param  \Illuminate\Http\Request                              $request
     * @param  string                                                $provider
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @param  \Laravel\Socialite\AbstractUser                       $socialUser
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    protected function providerAlreadyLinkedByAnotherAuthenticatable(
        Request $request,
        string $provider,
        Authenticatable $user,
        AbstractUser $socialUser
    ) {
        $provider = ucfirst($provider);
        \session()->flash('social', "Your {$provider} account is already linked to another account.");

        return \redirect()->intended(\route('home'));
    }

    /**
     * Handle the user redirect after linking.
     *
     * @param  \Illuminate\Http\Request                              $request
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @param  \Laravel\SocialAuthenticate\Models\SocialCredential   $socialCredential
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    protected function redirectAfterLink(
        Request $request,
        Authenticatable $user,
        SocialCredential $socialCredential
    ) {
        $provider = \ucfirst($socialCredential->provider_name);
        \session()->flash('social', "The {$provider} account has been linked to your account.");

        return redirect()->route('home');
    }

    /**
     * Handle the user redirect after unlinking.
     *
     * @param  \Illuminate\Http\Request                              $request
     * @param  string                                                $provider
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    protected function redirectAfterUnlink(Request $request, string $provider, Authenticatable $user)
    {
        $provider = \ucfirst($provider);
        \session()->flash('social', "The {$provider} account has been unlinked.");

        return redirect()->route('home');
    }

    /**
     * Handle the callback after the linking process.
     *
     * @param  \Illuminate\Http\Request                              $request
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @param  \Laravel\SocialAuthenticate\Models\SocialCredential   $socialCredential
     * @return mixed
     */
    protected function linked(Request $request, Authenticatable $user, SocialCredential $socialCredential)
    {
        //
    }

    /**
     * Handle the callback after the unlink process.
     *
     * @param  \Illuminate\Http\Request                              $request
     * @param  string                                                $provider
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @return mixed
     */
    protected function unlinked(Request $request, string $provider, Authenticatable $user)
    {
        //
    }
}
