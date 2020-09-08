<?php

namespace Laravel\SocialAuthenticate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Class Social Credential model
 *
 * @package     Laravel\SocialAuthenticate\Models
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
        'provider_id',
        'provider_name',
        'name',
        'nickname',
        'email',
        'avatar',
        'access_token',
        'refresh_token',
        'expires_at',
        'raw',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'raw' => 'array',
        'expires_at' => 'datetime',
    ];

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
