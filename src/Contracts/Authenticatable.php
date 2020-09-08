<?php

namespace Laravel\SocialAuthenticate\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\SocialAuthenticate\Models\SocialCredential;

/**
 * Interface authenticatable
 *
 * @package     Laravel\SocialAuthenticate\Contracts
 * @author      Oanh Nguyen <oanhnn.bk@gmail.com>
 * @license     The MIT license
 */
interface Authenticatable
{
    /**
     * Get the unique identifier for the user.
     *
     * @return int|string
     */
    public function getAuthIdentifier();

    /**
     * Get the entity's Social Credentials
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function socialCredentials(): MorphMany;

    /**
     * Check if the authenticatable instance has a Social account.
     *
     * @param  string  $provider
     * @return bool
     */
    public function hasSocialCredential(string $provider): bool;

    /**
     * Get the social account for a specific provider.
     *
     * @param  string  $provider
     * @return \Laravel\SocialAuthenticate\Models\SocialCredential|null
     */
    public function getSocialCredential(string $provider): ?SocialCredential;
}
