<?php

namespace Laravel\SocialAuthenticate\Event;

use Laravel\SocialAuthenticate\Contracts\Authenticatable;

class Unlinked
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
     * Create a new event instance.
     *
     * @param  \Laravel\SocialAuthenticate\Contracts\Authenticatable $user
     * @return void
     */
    public function __construct(Authenticatable $user, string $provider)
    {
        $this->user = $user;
        $this->provider = $provider;
    }
}
