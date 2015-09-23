<?php namespace Craft;

class AdvancedSearchVariable
{
    public function build($params)
    {
        return craft()->AdvancedSearch_search->build($params);
    }

    public function find($model)
    {
        return craft()->AdvancedSearch_search->find($model);
    }
}
