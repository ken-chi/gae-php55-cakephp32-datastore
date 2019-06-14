# gae-php55-cakephp32-datastore
use datastore like a cakephp model with GAE/PHP55.
Available in cakephp version 3.2.

usage:

----------------------------------------------------
use Gcp\Model\Table\DatastoreTable;
class ***Table extends DatastoreTable {
----------------------------------------------------

usage:

set key "authenticate" value "Gcp.Datastore"
----------------------------------------------------
config/app.php
'Auth' => [
        'authenticate' => 'Gcp.Datastore',
    ],
----------------------------------------------------
