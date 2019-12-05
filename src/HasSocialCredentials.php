<?php

namespace Laravel\SocialCredentials;

use Laravel\SocialCredentials\Models\SocialCredential;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait has social credentials
 *
 * @package     Laravel\SocialCredentials
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
}
