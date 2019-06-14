<?php
namespace Gcp\Controller\Component;

use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;
use Cake\I18n\Time;

/**
 * Util component
 */
class UtilComponent extends Component
{

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [];

    public function convert($data, Array $columns=[])
    {
        foreach ($columns as $k => $v) {
            // default value
            if (!isset($data[$k]) || $data[$k] == '') {
                if ($v) {
                    $data[$k] = (isset($v['default'])) ? $v['default'] : $data[$k];
                    if ($data[$k] == 'NOW') $data[$k] = Time::now()->format('Y/m/d H:i:s');
                } else {
                    unset($data[$k]);
                }
            }
            // convert
            if (isset($data[$k]) && is_string($data[$k])) $data[$k] = mb_convert_kana($data[$k], 'KV');
            if (isset($v['convert']) && $v['convert']) $data[$k] = preg_replace($v['convert'][0], $v['convert'][1], $data[$k]);
        }
        return $data;
    }
}
