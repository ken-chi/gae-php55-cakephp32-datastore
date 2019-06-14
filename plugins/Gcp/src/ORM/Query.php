<?php
namespace Gcp\ORM;

use Cake\Cache\Cache;
use Cake\Database\Query as DatabaseQuery;
use Cake\Datasource\QueryInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Log\Log;
use JsonSerializable;

class Query extends DatabaseQuery implements JsonSerializable, QueryInterface
{
    protected static $_datastore;
    private $__table;
    private $__type;
    private $__options;
    private $__query;
    private $__fulltext = [[]];
    protected $_hydrate = true;

    public function __construct($table, $type, $options)
    {
        $this->__table = $table;
        $this->__type = $type;
        $this->__options = $options;
        $this->__query = $table->_datastore->query()->kind($table->table());
        $this->__query->keysOnly();

        if (isset($options['hasAncestor'])) $this->__query->hasAncestor($hasAncestor);
        $cmd = ['conditions' => 'where'];
        foreach ($options as $query => $values) {
            if (!is_array($values)) continue;
            foreach ($values as $k => $v) {
                $method = $cmd[$query];
                $this->$method([$k => $v]);
            }
        }
    }

    public function select($fields = [], $overwrite = false)
    {
        return $this;
    }

    public function where($conditions = NULL, $types = [], $overwrite = false)
    {
        $columns = $this->__table->getColumns();
        foreach ($conditions as $k => $v) {
            $tmp = explode(' ', $k);
            $column = $tmp[0];
            $sign = (count($tmp) > 1) ? $tmp[1] : '=';

            if (isset($columns[$column]['fulltext'])) {
                $this->__fulltext[$column][] = $v; // fulltext search
            } else {
                $this->__query->filter($column, $sign, $v);
            }
        }
        return $this;
    }

    public function order($fields, $overwrite = false)
    {
        foreach ($fields as $k => $v) {
            $this->__query->order($k, $v);
        }
        return $this;
    }

    public function limit($num)
    {
        $this->__query->limit($num);
        return $this;
    }

    public function offset($num)
    {
        $this->__query->offset($num);
        return $this;
    }

    public function page($num, $limit = NULL)
    {
        $this->__query->offset($num);
        return $this;
    }

    public function all($options=[])
    {
        $this->__options = array_merge($this->__options, $options);
        $res = $this->__table->_datastore->runQuery($this->__query);

        $list = [];
        foreach ($res as $keys) {
            // return only keys of datastore.
            if (isset($this->__options['datastoreKeysOnly'])) {
                $list[] = $keys->key();
                continue;
            }
            $key = $keys->key()->pathEnd()['id'];

            // get cake entity from cache.
            $cache = !((isset($this->__options['datastore']) && $this->__options['datastore']) || !$this->__table->getIsCache());
            $entity = $cache ? Cache::read(sprintf('%s_%s', $this->__table->table(), $key)) : null;
            if (!$entity) {
                // get cake entity from datastore.
                $entity = $this->__table->newEntity(['id' => $key], ['useSetters' => false]);
                $store = $this->__table->_datastore->lookup($keys->key());

                // return datastore entity data when option datastore is true.
                if (isset($this->__options['datastore']) && $this->__options['datastore']) {
                    $list[] = $store;
                    continue;
                }

                foreach ($store->get() as $k => $v) {
                    $entity->$k = $v;
                }
                if ($cache) Cache::write(sprintf('%s_%s', $this->__table->table(), $key), $entity);
            }
            $entity->isNew(false);
            if ($this->__type == 'list') $list[$entity->id] = $entity;
            else if ($this->__type == 'array') {
                $list[] = $entity->toArray();
            }
            else $list[] = $entity;
        }
        return $list;
    }

    public function first($options=[])
    {
        $this->__query->limit(1);
        $res = $this->all($options);
        return (is_array($res) && count($res)) ? $res[0] : null;
    }

    public function count($options=[])
    {
        $res = $this->all($options);
        $count = 0;
        foreach ($res as $entity) $count++;
        return $count;
    }

    public function hydrate($enable = null)
    {
        if ($enable === null) {
            return $this->_hydrate;
        }

        $this->_dirty();
        $this->_hydrate = (bool)$enable;

        return $this;
    }

    public function jsonSerialize($options=[])
    {
        return json_encode($this->all($options));
    }

    protected function _dirty()
    {
        $this->_results = null;
        $this->_resultsCount = null;
        parent::_dirty();
    }

    public function aliasField($field, $alias = null){}
    public function aliasFields($fields, $defaultAlias = null){}
    public function applyOptions(array $options){}
    public function find($finder, array $options = []){}
    public function toArray(){}
    public function repository(RepositoryInterface $repository = null){}
}
