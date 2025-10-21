<?php

namespace App\Services\MenuSync\Contracts;

interface MenuProvider
{
    /**
     * Return an array of categories with items and modifiers in ONE locale.
     * Example shape:
     * [
     *   ['id'=>'c1','name'=>'Burgers','description'=>null,'image'=>null,
     *    'items'=>[
     *      ['id'=>'i9','name'=>'Classic','description'=>..., 'price'=>2.500, 'image_url'=>...,
     *       'addon_groups'=>[
     *         ['id'=>'g1','title'=>'Size','options'=>[['id'=>'o1','title'=>'Large','price'=>0.300], ...]]
     *       ]
     *      ],
     *    ]
     *   ],
     * ]
     */
    public function fetch(string $baseUrl, ?string $apiKey, string $locale): array;
}
