<?php

namespace Lavablog;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = [
        'iso2',
        'iso3',
        'sort'
    ];
}
