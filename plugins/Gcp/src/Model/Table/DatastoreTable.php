<?php
namespace Gcp\Model\Table;

use Cake\Validation\Validator;
use Cake\Cache\Cache;
use Cake\Datasource\EntityInterface;
use Cake\Core\Exception\Exception;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\I18n\Time;

use Cake\ORM\RulesChecker;
use Gcp\ORM\Query;

use Google\Cloud\Datastore\DatastoreClient;
use GuzzleHttp\Client;

class DatastoreTable extends Table{

    //const DEFAULT_VALIDATOR = 'default';
    public $_datastore;
    private $__transaction;
    protected $_columns;
    protected $_isCache = true;

    public function initialize(array $config)
    {
        $this->addBehavior('Gcp.Datastore');
        parent::initialize($config);
        $params = null;

        // connect Datastore
        if (isset($_SERVER['SERVER_SOFTWARE']) && 0 === strpos($_SERVER['SERVER_SOFTWARE'], 'Development/')) {
            if ($this->_datastore) return;
            $guzzleClient = new Client(['verify' => false,]);
            $handler = function ($request, array $options = []) use ($guzzleClient) {
                $options['sink'] = tmpfile();
                return $guzzleClient->send($request, $options);
            };
            $params = ['httpHandler' => $handler, 'authHttpHandler' => $handler, 'shouldSignRequest' => false];
        }
        $this->_datastore = ($params) ? new DatastoreClient($params) : new DatastoreClient();

        foreach($this->associations() as $ass) {
            if (preg_match('/BelongsTo$/', get_class($ass))) {
                $belongsTo = ['type' => 'select', 'list' => []];
                $res = $ass->target()->find('list')->all();
                foreach ($res as $entity) {
                    if (isset($entity->name)) $belongsTo['list'][$entity->id] = $entity->name;
                }
                $this->setColumn(strtolower($ass->name()).'_id', $belongsTo);
            }
        }
    }

    public function find($type = 'all', $options = []) {
        return new Query($this, $type, $options);
    }

    // new entity
    public function newEntity($data = [], array $options = []) {
        //return parent::newEntity($data, ['validate' => false]);
        // newEntity($data)だとRDBに繋げてしまうので直接定義
        $entity = parent::newEntity(null, $options);
        foreach ($data as $k => $v) $entity->$k = $v;
        return $entity;
    }

    // get entity
    public function get($key, $options=[]) {
        // Cache
        $cache = !((isset($options['datastore']) && $options['datastore']) || !$this->_isCache);
        $entity = $cache ? Cache::read(sprintf('%s_%s', $this->table(), $key)) : null;
        if ($entity) return $entity;

        // datastore entity
        $tmp = $this->_datastore->lookup($this->_datastore->key($this->table(), $key));
        if (!$tmp) return null;
        if (isset($options['datastore']) && $options['datastore']) return $tmp;

        // convert cake entity
        $data = ['id' => $tmp->key()->pathEnd()['id']];
        foreach ($tmp->get() as $k => $v) $data[$k] = $v;
        $entity = $this->newEntity($data);
        $entity->isNew(false);
        $entity->toEntity();
        if ($cache) Cache::write(sprintf('%s_%s', $this->table(), $key), $entity);
        return $entity;
    }

    // save entity
    public function save(EntityInterface $entity, $options = ['allowOverwrite' => true, 'validate' => 'default', 'modified' => true]) {
        // newEntity($data)だとRDBに繋げてしまうので直接validate
        $validationMethod = 'validation' . ucfirst((isset($options['validate'])) ? $options['validate'] : 'default');
        if (method_exists($this, $validationMethod)) {
            $validator = $this->$validationMethod(new Validator());
            Log::write('debug', print_r($entity, true));
            $errors = $validator->errors($entity->getOriginalValues());
            if ($errors) return $errors;
        }
        // newEntity($data)だとRDBに繋げてしまうので直接buildRule
        $success = $this->checkRules($entity, isset($entity->id) ? 'update' : 'create');
        if (!$success) return $entity->errors();

        // datastore entity
        if (!isset($options['modified']) || $options['modified']) $entity->modified = Time::now()->format('Y/m/d H:i:s');
        $this->transaction();
        try {
            if (isset($entity->id)) {
                $key = $this->_datastore->key($this->table(), $entity->id);
                $store = $this->__transaction->lookup($key);
                foreach ($entity->getOriginalValues() as $k => $v) {
                    if (!$entity->$k) continue;
                    if (isset($this->_columns[$k]['fulltext']['table'])) {
                        $this->_ngram($store[$k], $entity->$k, $k, $entity->id);
                    }
                    $store[$k] = $entity->$k;
                }
                //Log::write('debug', print_r($store, true));
                $res = $this->__transaction->update($store, $options);
            } else {
                unset($entity->id);
                $entity->created = Time::now()->format('Y/m/d H:i:s');
                $store = $this->_datastore->entity($this->table(), $entity->getOriginalValues());
                $res = $this->__transaction->insert($store, $options);
                $key = $store->key();
                foreach ($entity->getOriginalValues() as $k => $v) {
                    if (isset($this->_columns[$k]['fulltext']['table'])) {
                        $this->_ngram($store[$k], $entity->$k, $k, $key);
                    }
                }
            }
            $this->commit();
        } catch (Exception $e) {
            Log::write('debug', 'catch');
            $this->rollback();
            return ['database' => [$e->getMessage()]];
        }

        if (!$res) return false;

        Cache::delete(sprintf('%s_%s', $this->table(), $entity->id));
        return true;
    }

    // delete entity
    public function delete(EntityInterface $entity, $options = []) {
        //Log::write('debug', print_r($entity, true));
        $key = $this->_datastore->key($this->table(), $entity->id);
        Cache::delete(sprintf('%s_%s', $this->table(), $entity->id));
        Log::write('info', sprintf('DELETE(%s) : %s', $this->table(), json_encode($entity)));
        return $this->_datastore->delete($key);
    }

    public function deleteAll($conditions)
    {
        $query = $this->find()->where($conditions);
        $keys = $query->all(['datastoreKeysOnly' => 1]);
        foreach ($keys as $tmp) {
            $key = $tmp->pathEnd()['id'];
            $entity = Cache::delete(sprintf('%s_%s', $this->table(), $key));
            Log::write('info', sprintf('DELETE(%s) : %s', $this->table(), json_encode($entity)));
        }
        $this->_datastore->deleteBatch($keys);

        return count($keys);
    }

    // transaction
    public function transaction() {
        $this->__transaction = $this->_datastore->transaction();
    }

    // commit
    public function commit() {
        $this->__transaction->commit();
    }

    // rollback
    public function rollback() {
        $this->__transaction->rollback();
    }

    // get columns of table
    public function getColumns() {
        return $this->_columns;
    }

    // get columns of table
    public function setColumn($key, $val) {
        return $this->_columns[$key] = $val;
    }

    public function getIsCache() {
        return $this->_isCache;
    }

    protected function _ngram($storeStr, $entityStr, $col, $id, $num = 2)
    {
        $indexTable = $this->_columns[$col]['fulltext']['table'];
        $num = isset($this->_columns[$col]['fulltext']['ngram']) ? $this->_columns[$col]['fulltext']['ngram'] : 2;
        $original = $this->ngram($storeStr, $num);
        $new = $this->ngram($entityStr, $num);
        $delKeys = array_diff($original, $new);
        $addKeys = array_diff($new, $original);
        //Log::write('info', sprintf('ngram delKeys(%s) : %s', $indexTable, json_encode($delKeys)));
        //Log::write('info', sprintf('ngram addKeys(%s) : %s', $indexTable, json_encode($addKeys)));
        // delete index
        foreach ($delKeys as $delKey) {
            $key = $this->_datastore->key($indexTable, $delKey);
            $del = $this->__transaction->lookup($key);
            if (!isset($del['index'])) continue;
            $index = $del['index'];
            $i = array_search($id, $index);
            if ($i === false) continue;
            unset($index[$i]);
            $del['index'] = $index;
            if (count($index)) {
                Log::write('info', sprintf('ngram mod(%s) : %s', $indexTable, print_r($del, true)));
                if (!$this->__transaction->update($del)) throw new Exception(sprintf('Could not update index. %s %s %s', $indexTable, $delKey, $id));
            } else {
                Log::write('info', sprintf('ngram del(%s) : %s', $indexTable, print_r($del, true)));
                if (!$this->__transaction->delete($key)) throw new Exception(sprintf('Could not delete index. %s %s %s', $indexTable, $delKey, $id));
            }
        }

        // add index
        foreach ($addKeys as $addKey) {
            $key = $this->_datastore->key($indexTable, $addKey);
            $add = $this->__transaction->lookup($key);
            if (!$add) $add = $this->_datastore->entity($key, ['index' => []]);
            $index = $add['index'];
            $index[] = $id;
            //$index = array_unique($index);
            $add['index'] = $index;
            Log::write('info', sprintf('ngram add(%s) : %s', $indexTable, print_r($add, true)));
            if (!$this->__transaction->upsert($add)) throw new Exception(sprintf('Could not insert index. %s %s %s', $indexTable, $addKey, $id));
        }
    }
}
