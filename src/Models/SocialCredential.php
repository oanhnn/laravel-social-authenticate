<?php

namespace Laravel\SocialCredentials\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Class SocialCredential model
 *
 * @package     Laravel\SocialCredentials\Models
 * @author      Oanh Nguyen <oanhnn.bk@gmail.com>
 * @license     The MIT license
 */
class SocialCredential extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'social_cerdentials';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'access_token',
        'avatar',
        'email',
        'expires_at',
        'name',
        'nickname',
        'provider_id',
        'provider_name',
        'refresh_token',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['expires_at'];

    /**
     * The owner of this cerdential
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('model');
    }
}
