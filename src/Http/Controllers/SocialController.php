<?php

namespace Laravel\SocialAuthenticate\Http\Controller;

use Laravel\SocialAuthenticate\HandleSocialAuthenticate;
use Laravel\SocialAuthenticate\HandleSocialLink;

class SocialController
{
    use HandleSocialAuthenticate;
    use HandleSocialLink;
}
