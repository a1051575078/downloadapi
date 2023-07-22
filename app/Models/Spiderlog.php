<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Spiderlog extends Model
{
    use HasFactory;
    public $timestamps=false;
    protected $fillable = [
        'content',
        'spider_id',
        'ip',
        'time'
    ];
}
