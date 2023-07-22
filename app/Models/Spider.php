<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Spider extends Model
{
    use HasFactory;
    public $timestamps=false;
    protected $fillable = [
        'name'
    ];
    public function logs(): HasMany{
        return $this->hasMany(Spiderlog::class);
    }
}
