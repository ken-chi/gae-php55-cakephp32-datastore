<?php
namespace Gcp\Controller;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Core\Exception\Exception;
use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use Cake\Log\Log;

/**
 * Datastore Scaffold Controller
 */
class DatastoreController extends Controller
{
    public function initialize()
    {
        parent::initialize();
        Plugin::load('Gcp');

        $this->loadComponent('RequestHandler');
        $this->loadComponent('Flash');
        $this->loadComponent('Paginator');
        $this->loadComponent('Auth', Configure::read('Auth'));
        $this->loadComponent('Gcp.Util');

        //$this->viewBuilder()->plugin('Gcp');
        $this->viewBuilder()->className('Gcp.Datastore');
        $this->autoRender = false;
    }

    /*
     * View List
     * @param int p page
     * @param int limit limit
     * @param int q query(key__value) only one value
     */
    public function index()
    {
        $data = $this->request->query;

        // page
        $page = (isset($data['p'])) ? $data['p'] : 0;
        $this->set('p', $page);

        // limit
        $confLimit = Configure::read('DatastoreScaffold', []);
        $defaultLimit = (isset($confLimit['defaultLimit'])) ? $confLimit['defaultLimit'] : 10;
        $maxLimit = (isset($confLimit['maxLimit'])) ? $confLimit['maxLimit'] : 30;
        $limit = (isset($data['limit'])) ? $data['limit'] : $defaultLimit;
        if ($limit > $maxLimit) $limit = $maxLimit;
        $this->set('limit', $limit);

        // search query
        $this->set('q', null);

        $filters = [];
        if ($this->Auth->user()['role'] !== 'root') $filters['groups_id'] = $this->Auth->user()['groups_id'];
        if (isset($data['q']) && $data['q']) {
            $conditions = explode('__', $data['q']);
            if (count($conditions) > 1) {
                $key = $conditions[0];
                $val = $conditions[1];
                if (count($conditions) > 2 && $conditions[2] == 'equal') {
                    $filters[$key . ' ='] = $val;
                } else {
                    if ($val) $filters[$key . ' >='] = $val;

                    // If argument is 3 or more, range search. 2, forward match search.
                    if (count($conditions) > 2) {
                        if ($conditions[2]) $filters[$key . ' <='] = $conditions[2];
                    } else {
                        $filters[$key . ' <='] = $val . UTF_END;
                    }
                }
            }
            $this->set('q', $data['q']);
            $this->set($key, $val);
        }

        try {
            $table = TableRegistry::get($this->name);
            $this->set('columns', $table->getColumns());
            $this->set('table', $table);

            // List the entity
            $query = $table->find('all');
            $query->where($filters);
            $query->limit($limit);
            $query->offset($page * $limit);
            $res = $query->all();
            $this->set('list', $res);

            $count = $query->count();
            $this->set('count', $count);

        } catch (\Exception$e) {
            $this->Flash->error($e->getMessage());
        }

        $this->render('Gcp.Datastore/default', null);
    }

    /*
     * View List
     * @param mix * value
     */
    public function regist()
    {
        $this->response->type('json');
        $table = TableRegistry::get($this->name);
        $data = $this->Util->convert($this->request->data, $table->getColumns());
        //Log::write('debug', print_r($data, true));
        $options = ['allowOverwrite' => true];
        $res = ['res' => 'success', 'message' => 'Updated.'];

        try {
            if (isset($data['id']) && $data['id'] == '') unset($data['id']);
            if (!isset($data['id'])) {
                $res['message'] = 'Inserted.';
                $options = [];
            } else {
                $options['validate'] = 'update';
            }
            $entity = $table->newEntity($data, $options);
            if (isset($data['id'])) $entity->isNew(false);
            $errors = $table->save($entity, $options);
            if (is_array($errors)) throw new Exception(json_encode($errors));

            $this->response->body(json_encode($res + ['res' => 'success']));
        } catch (\Exception$e) {
            $this->response->body(json_encode(['res' => 'error', 'message' => json_decode($e->getMessage())]));
        }
    }

    public function delete()
    {
        $data = $this->request->data;
        try {
            if (!isset($data['id']) || !$data['id']) throw new Exception('No key.');

            $table = TableRegistry::get($this->name);

            $targets = explode(',', $data['id']);
            foreach ($targets as $id) {
                $entity = $table->get($id);
                $table->delete($entity);
            }

            $this->response->type('json');
            $this->response->body(json_encode(['res' => 'success', 'message' => 'Deleted.']));
        } catch (\Exception$e) {
            $this->response->body(json_encode(['res' => 'error', 'message' => $e->getMessage()]));
        }
    }

    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        $user = $this->Auth->user();
        $this->set('username', $user['username']);
        if (!$user) return;

        // check role
        $menu = Configure::read('AdminMenu');
        $cont = mb_strtolower($this->name);
        if (isset($menu[$cont]['roles']) && is_array($menu[$cont]['roles'])) {
            $isAuth = (isset($user['role']) && in_array($user['role'], $menu[$cont]['roles'])) ? true : false;
            if (!$isAuth) $this->redirect('/');
        }
    }
}
