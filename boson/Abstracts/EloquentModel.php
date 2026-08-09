<?php namespace Boson\Abstracts;
/**
* @name      Boson PHP micro framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @link      http://alex-tisch.ru
* @copyright Copyright (c) 2018 All rights reserved
*/

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Boson\Traits\ClassName;

/**
 * @name      Boson PHP micro framework
 * @author    Tishchenko Alexander (info@alex-tisch.ru)
 * @link      http://alex-tisch.ru
 * @copyright Copyright (c) 2018 All rights reserved
 */
abstract class EloquentModel extends Model implements \JsonSerializable
{
    use ClassName;

    /**
     * EloquentModel constructor.
     * @param array $attributes
     * @param string|null $table
     */
    public function __construct(array $attributes = [], ?string $table = null)
    {
        if( !empty($table) ) {
            $this->setTable($table);
        }
        
        parent::__construct($attributes);
    }

    /**
     * Order by random row
     * @param Builder $query
     * @return Builder
     */
    public function scopeOrderByRandom(Builder $query): Builder
    {
        // Для MySQL: RAND(), для PostgreSQL: RANDOM()
        $driver     = $query->getConnection()->getDriverName();
        $randomFunc = $driver === 'pgsql' ? 'RANDOM()' : 'RAND()';
        
        return $query->orderByRaw($randomFunc);
    }

    /**
     * Where LIKE (безопасно)
     * @param Builder $query
     * @param string $fieldname
     * @param string $str
     * @return Builder
     */
    public function scopeWhereLike(Builder $query, string $fieldname, string $str): Builder
    {
        return $query->where($fieldname, 'LIKE', '%' . $str . '%');
    }

    /**
     * Or Where LIKE (безопасно)
     * @param Builder $query
     * @param string $fieldname
     * @param string $str
     * @return Builder
     */
    public function scopeOrWhereLike(Builder $query, string $fieldname, string $str): Builder
    {
        return $query->orWhere($fieldname, 'LIKE', '%' . $str . '%');
    }

    /**
     * Where Fulltext Match (MySQL)
     * @param Builder $query
     * @param string|array $fieldname
     * @param string $searchtext
     * @return Builder
     */
    public function scopeWhereFulltextMatch(Builder $query, $fieldname, string $searchtext): Builder
    {
        $fields = is_array($fieldname) ? implode(',', $fieldname) : $fieldname;
        
        return $query->whereRaw("MATCH({$fields}) AGAINST(? IN BOOLEAN MODE)", [$searchtext]);
    }

    /**
     * Or Where Fulltext Match (MySQL)
     * @param Builder $query
     * @param string|array $fieldname
     * @param string $searchtext
     * @return Builder
     */
    public function scopeOrWhereFulltextMatch(Builder $query, $fieldname, string $searchtext): Builder
    {
        $fields = is_array($fieldname) ? implode(',', $fieldname) : $fieldname;
        
        return $query->orWhereRaw("MATCH({$fields}) AGAINST(? IN BOOLEAN MODE)", [$searchtext]);
    }

    /**
     * JSON serialization
     * @return array
     */
    public function jsonSerialize(): array
    {
        return method_exists($this, 'transform') ? $this->transform() : $this->toArray();
    }
}