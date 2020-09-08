<?php

namespace Laravel\SocialAuthenticate\Event;

use Laravel\SocialAuthenticate\Contracts\Authenticatable;
use Laravel\SocialAuthenticate\Models\SocialCredential;

class Linked
{
    /**
     * The authenticated user.
     *
     * @var \Laravel\SocialAuthenticate\Contracts\Authenticatable
     */
    public $user;

    /**
     * Provider name
     *
     * @var string
     */
    public $provider;

    /**
     * Social credential
     *
     * @var \Laravel\SocialAuthenticate\Models\SocialCredential
     */
    public $socialCredential;


    /**
     * Create a new event instance.
     *
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @return void
     */
    public function __construct(Authenticatable $user, string $provider, SocialCredential $socialCredential)
    {
        $this->user = $user;
        $this->provider = $provider;
        $this->socialCredential = $socialCredential;
    }
}
