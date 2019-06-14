# gae-php55-cakephp32-datastore
use datastore like a cakephp model with GAE/PHP55.
Available in cakephp version 3.2.

usage:

TableClass for datastore
extends Gcp\Model\Table\DatastoreTable.
----------------------------------------------------
use Gcp\Model\Table\DatastoreTable;
class ***Table extends DatastoreTable {
----------------------------------------------------

usage:

AuthComponent for users table on datastore
set key "authenticate" value "Gcp.Datastore"
----------------------------------------------------
config/app.php
'Auth' => [
        'authenticate' => 'Gcp.Datastore',
    ],
----------------------------------------------------
