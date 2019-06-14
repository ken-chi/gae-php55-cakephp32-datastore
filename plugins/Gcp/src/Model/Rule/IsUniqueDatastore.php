<?php
namespace Gcp\Model\Rule;

use Cake\Datasource\EntityInterface;
use Cake\Log\Log;

class IsUniqueDatastore
{
    private $__table;
    public function __construct($field, $table, $option=[])
    {
        $this->_field = $field;
        $this->__table = $table;
    }

    public function __invoke(EntityInterface $entity, array $options)
    {
        if (!$entity->get($this->_field)) return false;
        $query = $this->__table->find()->where([$this->_field => $entity->get($this->_field)]);
        $count = $query->count();
        return $entity->isNew() ? !($count) : ($count === 1);
    }
}
