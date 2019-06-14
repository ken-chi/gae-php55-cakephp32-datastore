<?php
namespace Gcp\View\Helper;

use Cake\View\Helper;
use Cake\View\View;
use Cake\Validation\ValidationSet;

class DatastoreHelper extends Helper
{

    public $helpers = ['Form'];
    protected $_defaultConfig = [];

    /*
     * view validation rules
     * @param ValidationSet $val
     * @param string $pieces
     * @return string
     */
    public function inputTypes(ValidationSet $val, $pieces = ', ')
    {
        if (!$val) return '';
        $ness = (!$val->isEmptyAllowed()) ? '*' : '';
        $types = [];
        foreach ($val->rules() as $k => $v) {
            $pass = $v->get('pass');
            if ($k == 'size' && is_array($pass) && count($pass)) {
                $pass = sprintf('(%s-%s)', $v->get('pass')[0], (count($pass)>1) ? $v->get('pass')[1] : '');
            } else if (in_array($k, ['minLength', 'maxLength']) && count($pass)) {
                $pass = sprintf('(%s)', $v->get('pass')[0]);
            } else $pass = '';
            $types[] = $k . $pass;
        }
        return $ness . implode($pieces, $types);
    }

    // Create input from DatastoreTable._columns
    public function input($name, $option=[])
    {
        $option['value'] = (isset($option['value']) && $name != 'password') ? $option['value'] : '';
        // DatastoreTable._columns
        $conf = (isset($option['conf'])) ? $option['conf'] : null;
        unset($option['conf']);
        $option['class'] .= ' form-control form-control-sm col-auto';

        $type = 'text';
        $list = [];
        if ($conf) {
            if (isset($conf['type']) && $conf['type']) {
                // input type
                $type = $conf['type'];

                // set select options
                if (isset($conf['list']) && is_array($conf['list'])) {
                    foreach ($conf['list'] as $k => $v) $list[$k] = $v;
                }
            }
        }
        $res = null;
        if (in_array($type, ['select', 'checkbox'])) $res = $this->Form->$type($name, $list, $option);
        else if (in_array($type, ['dateTime'])) {
            $option['class'] .= ' datetimepicker';
            $res = $this->Form->text($name, $option);
        } else $res = $this->Form->$type($name, $option);
        return $res;
    }
}
