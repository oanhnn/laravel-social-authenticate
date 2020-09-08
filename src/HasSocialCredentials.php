<?php

namespace Laravel\SocialAuthenticate;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\SocialAuthenticate\Models\SocialCredential;

/**
 * Trait HasSocialCredentials for user model
 *
 * @package     Laravel\SocialAuthenticate
 * @author      Oanh Nguyen <oanhnn.bk@gmail.com>
 * @license     The MIT license
 */
trait HasSocialCredentials
{
    /**
     * Define a polymorphic one-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $name
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    abstract public function morphMany($related, $name, $type = null, $id = null, $localKey = null);

    /**
     * Get the entity's Social Credentials
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function socialCredentials(): MorphMany
    {
        return $this->morphMany(SocialCredential::class, 'model');
    }

    /**
     * Check if the authenticatable instance has a Social account.
     *
     * @param  string  $provider
     * @return bool
     */
    public function hasSocialCredential(string $provider): bool
    {
        return $this->socialCredentials()
            ->where('provider_name', $provider)
            ->exists();
    }

    /**
     * Get the social account for a specific provider.
     *
     * @param  string  $provider
     * @return \Laravel\SocialAuthenticate\Models\SocialCredential|null
     */
    public function getSocialCredential(string $provider): ?SocialCredential
    {
        return $this->socialCredentials()
            ->where('provider_name', $provider)
            ->first();
    }
}
