<?php namespace Craft;

use Craft\AdvancedSearch_Helper as Search;

/**
 * Contains the criteria for performing an advanced search
 *
 * @todo : Support mapping of query string parameters to a search attribute array
 *         (i.e. 'search', 'filter', 'category') without requiring the attribute
 *         prefix in the query string (i.e. just 'field' instead of 'search[field] ')
 */

class AdvancedSearch_Model extends BaseModel
{
    public $count_groups;
    public $count_items;
    protected $search;
    protected $filter;
    protected $category;
    protected $_custom_attributes = [
        'search',
        'filter',
        'category',
    ];
    protected $defaults = [
        'order' => 'title asc',
    ];

    protected function defineAttributes()
    {
        return [
            'use_query' => AttributeType::Bool,
            'section' => AttributeType::String,
            'order' => AttributeType::SortOrder,
            'page' => AttributeType::Number,
            'per_page' => AttributeType::Number,
            'group' => AttributeType::String,
            'search' => AttributeType::Mixed,
            'filter' => AttributeType::Mixed,
            'category' => AttributeType::Mixed,
            'include' => AttributeType::Mixed,
            'map' => AttributeType::Mixed,
        ];
    }

    /**
     * Add criteria to the model, from either a user-defined array
     * or from an the request's query string array
     *
     * @param  Array  $criteria
     */
    public function addCriteria(Array $criteria)
    {
        foreach($criteria as $attribute => $setting) {

            if (in_array($attribute, $this->_custom_attributes)) {

                foreach($setting as $field => $value) {

                    if (is_array($value)) {
                        $this->addFieldMapping($field, $value);
                        $value = Search::array_get($value, 'value');
                    }

                    $this->addSearchElement($attribute, [$field => $value]);
                }

            } else {
                $this->setAttribute($attribute, $setting);
            }
        }

        return $this;
    }

    private function addFieldMapping($field, $value)
    {
        $map_to_field = Search::array_get($value, 'field');

        if ($map_to_field) {
            $this->addElement('map', [$field => $map_to_field]);
        }
    }

    /**
     * Set any default criteria if they were not already set on the model
     */
    public function setDefaults()
    {
        foreach($this->defaults as $attribute => $default) {
            $this->$attribute = $this->$attribute ?: $default;
        }

        return $this;
    }

    /**
     * Add an array of query terms to custom search and filter attributes,
     * taking field name mappings into account
     *
     * @param  String  $attribute
     * @param  Array  $element
     */
    private function addSearchElement($attribute, Array $element)
    {
        $field = $this->mapFieldName(key($element));
        $query_element = &$this->{$attribute}[$field];

        if (!isset($query_element)) {
            $query_element = [];
        }

        $value = current($element);

        if ($value && !in_array($value, $query_element) ) {
            $query_element[] = $value;
        }
    }

    /**
     * Add a key/value pair to a regular model attribute
     *
     * @param   String  $attribute Attribute name on this model
     * @param   Array   $element
     */
    private function addElement($attribute, Array $element)
    {
        $this->$attribute = array_merge((array) $this->$attribute, $element);
    }

    /**
     * Map query keys to field names if a mapping has been defined
     *
     * @param   String  $field
     */
    private function mapFieldName($key)
    {
        return Search::array_get($this->map, $key, $key);
    }

    /**
     * Set model attributes using an array of attributes and values
     *
     * @param  Array  $attributes
     */
    public function set(Array $attributes)
    {
        foreach($attributes as $attribute => $value) {
            $this->$attribute = $value;
        }
    }

    /**
     * Add criteria, in array form, to a model attribute
     *
     * @param  Array  $attributes
     */
    public function add(Array $attributes)
    {
        foreach($attributes as $attribute => $criteria) {
            $this->addElement($attribute, $criteria);
        }
    }

    /**
     * Dynamically call set or add with name of attribute
     * (e.g. setGroup, addFilter)
     *
     */
    public function __call($name, $args)
    {
        list($set, $parameter) = array_pad(explode('set', $name, 2), 2, '');
        if ($parameter) {
            $this->set([lcfirst($parameter) => $args[0]]);
        }

        list($add, $parameter) = array_pad(explode('add', $name, 2), 2, '');
        if ($parameter) {
            $this->add([lcfirst($parameter) => [$args[0] => $args[1] ] ]);
        }
    }

    /**
     * Extend getAttribute from BaseModel to return locally-defined attributes,
     * allowing more complete use of array functions when working with nested attributes
     *
     * @param string $name
     * @param bool   $flattenValue
     */
    public function getAttribute($name, $flattenValue = false)
    {
        if (in_array($name, $this->_custom_attributes)) {
            return $this->$name;
        }

        return parent::getAttribute($name, $flattenValue);
    }

    /**
     * Return counts of search results
     */
    public function count()
    {
        return $this->count_groups ?: $this->count_items;
    }

    public function countGroups()
    {
        return $this->count_groups;
    }

    public function countItems()
    {
        return $this->count_items;
    }

    public function setCounts(Array $counts)
    {
        $this->count_groups = Search::array_get($counts, 'count_groups');
        $this->count_items = Search::array_get($counts, 'count_items');
    }

    /**
     * Trigger find in service
     */
    public function find()
    {
        return craft()->AdvancedSearch_search->find($this);
    }

    /**
     * Return a paginator instance
     */
    public function getPaginator()
    {
        return new AdvancedSearch_PaginatorModel($this);
    }

    /**
     * Output search criteria, optionally breaking execution
     *
     * @param   boolean  $break
     */
    public function debug($break = false)
    {
        return craft()->AdvancedSearch_search->debug($this, $break);
    }
}
