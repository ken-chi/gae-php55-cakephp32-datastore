<?php
/**
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         2.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Gcp\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\ORM\TableRegistry;
use Cake\Log\Log;
use Cake\I18n\Time;

/**
 * An authentication adapter for AuthComponent. Provides the ability to authenticate using POST
 * data. Can be used by configuring AuthComponent to use it via the AuthComponent::$authenticate config.
 *
 * ```
 *  $this->Auth->authenticate = [
 *      'Form' => [
 *          'finder' => ['auth' => ['some_finder_option' => 'some_value']]
 *      ]
 *  ]
 * ```
 *
 * When configuring FormAuthenticate you can pass in config to which fields, model and additional conditions
 * are used. See FormAuthenticate::$_config for more information.
 *
 * @see \Cake\Controller\Component\AuthComponent::$authenticate
 */
class DatastoreAuthenticate extends BaseAuthenticate
{
    public function authenticate(Request $request, Response $response)
    {
        $fields = $this->_config['fields'];

        return $this->_findUser(
            $request->data[$fields['username']],
            $request->data[$fields['password']]
        );
    }

    /**
     * Find a user record using the username and password provided.
     *
     * Input passwords will be hashed even when a user doesn't exist. This
     * helps mitigate timing attacks that are attempting to find valid usernames.
     *
     * @param string $username The username/identifier.
     * @param string|null $password The password, if not provided password checking is skipped
     *   and result of find is returned.
     * @return bool|array Either false on failure, or an array of user data.
     */
    protected function _findUser($username, $password = null)
    {
        $result = $this->_query($username, true)->first();

        if (empty($result)) {
            return false;
        }

        if ($password !== null) {
            $hasher = $this->passwordHasher();
            $hashedPassword = $result->get()[$this->_config['fields']['password']];
            if (!$hasher->check($password, $hashedPassword)) {
                //Log::write('debug', sprintf('check NG : %s %s', $username, $hashedPassword));
                return false;
            }
            //Log::write('debug', sprintf('check OK : %s %s', $username, $hashedPassword));

            $this->_needsPasswordRehash = $hasher->needsRehash($hashedPassword);
            $passwordField = $this->_config['fields']['password'];
            unset($result->$passwordField);
            //Log::write('debug', sprintf('needsRehash %s', $this->_needsPasswordRehash));
        }

        // update last_login_at
        $entity = $this->_query($username)->first();
        $entity->last_login_at = Time::now()->format('Y/m/d H:i:s');
        unset($entity->password);
        //Log::write('debug', sprintf('last_login_at %s', print_r($entity, true)));

        $config = $this->_config;
        $table = TableRegistry::get($config['userModel']);
        $table->save($entity, ['allowOverwrite' => true, 'modified' => false]);

        return $entity->toArray();
    }

    /**
     * Get query object for fetching user from database.
     *
     * @param string $username The username/identifier.
     * @return \Cake\ORM\Query
     */
    protected function _query($username, $isDatastore=false)
    {
        $config = $this->_config;
        $table = TableRegistry::get($config['userModel']);

        $options = [
            'conditions' => [$config['fields']['username'] => $username],
            'datastore' => $isDatastore,
        ];

        if (!empty($config['scope'])) {
            $options['conditions'] = array_merge($options['conditions'], $config['scope']);
        }
        if (!empty($config['contain'])) {
            $options['contain'] = $config['contain'];
        }

        $finder = $config['finder'];
        if (is_array($finder)) {
            $options += current($finder);
            $finder = key($finder);
        }

        if (!isset($options['username'])) {
            $options['username'] = $username;
        }

        $query = $table->find($finder, $options);

        return $query;
    }
}
