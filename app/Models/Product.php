<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = "products";

    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'sku',
        'status',
        'variations',
        'price',
        'quantity',
        'currency'
    ];

    protected $dates = ['deleted_at'];
}
