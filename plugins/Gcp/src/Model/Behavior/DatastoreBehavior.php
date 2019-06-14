<?php
namespace Gcp\Model\Behavior;

use Cake\Core\Configure;
use Cake\ORM\Behavior;

class DatastoreBehavior extends Behavior
{
    public function ngram($str, $num)
    {
        if ($num < 1) {
            return $str;
        }

        if ($num < 2) {
            $_chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
            return $_chars;
        }

        $app = Configure::read('App');
        $enc = isset($app['encoding']) ? $app['encoding'] : 'UTF-8';
        $_len = mb_strlen($str, $enc);
        $_items = [];
        for ($i = 0; $i <= $_len - $num; $i++) {
            $_items[] = mb_substr($str, $i, $num, $enc);
        }

        return $_items;
    }
}
