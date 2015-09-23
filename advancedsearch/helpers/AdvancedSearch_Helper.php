<?php namespace Craft;

use Craft\AdvancedSearch_Model;

class AdvancedSearch_Helper
{
    /**
     * Helper for returning array value if set
     *
     * @param   Array  $array
     * @param   Mixed  $key
     * @param   Mixed  $default
     */
    public static function array_get($array, $key, $default = null)
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }

    public static function show_criteria(AdvancedSearch_Model $model, $debug = false)
    {
        echo('<pre>');
        print_r($model->getAttributes());
        echo('</pre>');

        if ($debug) {
            Craft::dd('Done.');
        }
    }
}
