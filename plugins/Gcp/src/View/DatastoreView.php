<?php
namespace Gcp\View;

use Cake\View\View;

class DatastoreView extends View
{
    public function initialize()
    {
      parent::initialize();
      $this->loadHelper('Gcp.Datastore');
    }
}
