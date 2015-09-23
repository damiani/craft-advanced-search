<?php namespace Craft;

use Craft\AdvancedSearch_Helper as Search;
use Craft\AdvancedSearch_Model;

/**
 * Create an 'advanced' search model and extend the ElementCriteriaModel
 * to build a query based on the criteria specified in the model.
 *
 * @todo : Extract ECM and DbCommand methods to separate objects,
 *         refactor to simplify complicated sequential chains like `find()`
 *
 * @todo : Add event hooks, e.g. to allow users to modify queries before they are run
 *
 */

class AdvancedSearch_SearchService extends BaseApplicationComponent
{
    /**
     * Build the criteria for performing a search
     *
     * @param   Array  $criteria
     * @return  AdvancedSearch_Model
     */
    public function build($criteria = null)
    {
        $query = Search::array_get($criteria, 'use_query') ? craft()->request->getQuery() : [];

        $model = new AdvancedSearch_Model();
        $model->addCriteria($criteria)->addCriteria($query)->setDefaults();

        $page = craft()->request->getPageNum();

        if ($page && ! $model->getAttribute('page')) {
            $model->setPage($page);
        }

        return $model;
    }

    /**
     * Find entries based on the search criteria
     *
     * @todo : After addPagination, set count on model if it wasn't already set by addGroupBy
     *
     * @todo : When grouping results, return a nested result array, so we
     *         don't need to use the Twig '| group()' filter in the template
     *
     * @param   AdvancedSearch_Model  $model
     * @return  EntryModel
     */
    public function find(AdvancedSearch_Model $model)
    {
        $criteria = craft()->elements->getCriteria(ElementType::Entry);

        $criteria->section = $model->getAttribute('section');
        $criteria = $this->addFilterQueries($model, $criteria);
        $criteria = $this->addRelatedToQueries($model, $criteria);
        $criteria = $this->addSearchQueries($model, $criteria);

        $dbCommand = $this->extendElementCriteriaModel($criteria);

        if (! $dbCommand) {
            // If dbCommand comes back empty, nothing matched the search
            // ('search' query is triggered by buildElementsQuery)...
            // so we can return an empty result immediately.
            return EntryModel::populateModels(null);
        }

        $dbCommand = $this->joinIncludedTables($model, $dbCommand);

        $table_prefix = 'content';

        if ($dbCommand->having) {
            $table_prefix = 'groups';
            $bound_parameters = $dbCommand->params;
            $dbCommand = $this->addOuterGroupBy($model, $dbCommand, $table_prefix);
        } else {
            $dbCommand = $this->addCount($dbCommand);
            $dbCommand = $this->addGroupBy($model, $dbCommand, $table_prefix);
        }

        $dbCommand = $this->addOrderBy($model, $dbCommand, $table_prefix);
        $dbCommand = $this->addPagination($model, $dbCommand, $table_prefix);

        if (isset($bound_parameters)) {
            $dbCommand->bindValues($bound_parameters);
        }

        return EntryModel::populateModels($this->getQueryResult($model, $dbCommand));
    }

    /**
     * Add ORDERBY clause if order attribute was specified on model
     *
     * @todo : If grouping, ORDERBY needs to be nested inside a subquery
     *         so it executes before GROUPBY for accurate ordering
     *
     * @param  AdvancedSearch_Model  $model
     * @param  dbCommand        $dbCommand
     * @param  String           $prefix
     */
    private function addOrderBy(AdvancedSearch_Model $model, dbCommand $dbCommand, $prefix)
    {
        $dbCommand->order = $this->prefixDbColumn($model->getAttribute('order'), $prefix);

        return $dbCommand;
    }

    /**
     * Convert comma-separated list of items into an array of items
     *
     * @param   String  $item_string
     */
    private function csvToArray($item_string, $key = 'id')
    {
        return array_map(function ($item) use ($key) {
                return [$key => $item];
            }, explode(',', $item_string));
    }

    /**
     * Add relatedTo queries, for categories
     *
     * @todo : check if query is using ids or slugs; if categories are not specified as ids,
     *         use search instead, to avoid having to look up ids in a separate query.
     *         e.g.: $criteria->search = 'professionalType:attorney';
     *
     * @todo : allow specifying field names to use for relation
     *         e.g.: ['targetElement' => '8888', 'field' => 'professionalType'];
     *         (though it's best not to use them, for a faster query)
     *
     * @todo : allow OR category searches
     *
     * @todo : add support for tags
     *
     * @param  AdvancedSearch_Model  $model
     * @param  ElementCriteriaModel  $criteria
     */
    private function addRelatedToQueries(AdvancedSearch_Model $model, ElementCriteriaModel $criteria)
    {
        $categories = (array) $model->getAttribute('category');
        $category_query = ['and'];

        foreach($categories as $field => $value) {
            if (isset($value[0])) {
                $category_query[] = ['targetElement' => $value[0]];
            }
        }

        if (count($category_query) > 1) {
            $criteria->relatedTo = $category_query;
        }

        return $criteria;
    }

    /**
     * Add field-specific filters
     *
     * @param  AdvancedSearch_Model  $model
     * @param  ElementCriteriaModel  $criteria
     */
    private function addFilterQueries(AdvancedSearch_Model $model, ElementCriteriaModel $criteria)
    {
        $filters = (array) $model->getAttribute('filter');

        foreach($filters as $field => $value) {

            if (current($value)) {
                $criteria->$field = current($value);
            }
        }

        return $criteria;
    }

    /**
     * Add search (full-text) queries
     * e.g. 'title:adoption OR title:smith AND state:CA AND zip:93010';
     *
     * @param  AdvancedSearch_Model  $model
     * @param  ElementCriteriaModel  $criteria
     */
    private function addSearchQueries(AdvancedSearch_Model $model, ElementCriteriaModel $criteria)
    {
        $searches = (array) $model->getAttribute('search');
        $search_query = '';

        foreach($searches as $field => $values) {

            // handle OR searches, denoted with |-separated field names
            $fields = explode('|', $field);

            foreach($values as $value) {

                $search_subquery = '';

                foreach($fields as $field) {
                    $search_subquery .= " OR $field:'$value'";
                }

                $search_query .= ' AND ' . substr($search_subquery, 4);
            }
        }

        $search_query = substr($search_query, 5);

        if ($search_query) {
            $criteria->search = $search_query;
        }

        return $criteria;
    }

    /**
     * Convert ElementCriteriaModel to dbCommand to customize query
     * and specify remaining search criteria
     *
     * @param   ElementCriteriaModel  $criteria
     */
    private function extendElementCriteriaModel(ElementCriteriaModel $criteria)
    {
        $where_clauses = $this->collectExtraWhereClauses($criteria);
        $dbCommand = craft()->elements->buildElementsQuery($criteria);

        return $dbCommand ? $this->restoreExtraWhereClauses($dbCommand, $where_clauses) : null;
    }

    /**
     * Any extra fields that need to be joined from other tables get
     * stripped out when calling buildElementsQuery; grab them here, so
     * they can be manually add to the DbCOmmand after converting from ECM.
     *
     * @param   ElementCriteriaModel  $criteria
     */
    private function collectExtraWhereClauses(ElementCriteriaModel $criteria)
    {
        $extra_attributes = array_flip($criteria->getExtraAttributeNames());

        foreach($extra_attributes as $field => &$value) {
            $value = $criteria->getAttribute($field);
        }

        return $extra_attributes;
    }

    /**
     * Restore where clauses for extra attributes
     *
     * @param   DbCommand  $dbCommand
     * @param   Array      $extra_attributes
     */
    private function restoreExtraWhereClauses(DbCommand $dbCommand, Array $extra_attributes)
    {
        $extra = 0;
        foreach($extra_attributes as $field => $value) {
            // ignore bound parameters in array form; for Smart Map plugin,
            // possibly others that build their own queries upon conversion
            // from ECM to DbCommand; investigate adding this as a setting,
            // i.e. ignoring conventional 'where' clause if handled by another plugin.
            if (!is_array($value)) {
                $extra++;
                $dbCommand->andWhere("$field=:extra$extra", [":extra$extra" => $value]);
            }
        }

        return $dbCommand;
    }

    /**
     * Join extra tables specified in 'include' setting
     *
     * @todo : allow specifying id field for the join, in the event it's not 'elementId'
     *
     * @param   AdvancedSearch_Model   $model
     * @param   dbCommand         $dbCommand
     */
    private function joinIncludedTables(AdvancedSearch_Model $model, dbCommand $dbCommand)
    {
        $includes = (array) $model->getAttribute('include');

        foreach($includes as $alias => $table) {
            $dbCommand->leftJoin("$table $alias", "$alias.elementId = entries.id");
        }

        return $dbCommand;
    }

    /**
     * Include count in outer SELECT statement
     *
     * @param  dbCommand        $dbCommand
     */
    private function addCount(dbCommand $dbCommand)
    {
        return $dbCommand->addSelect('count(*) AS count');
    }

    /**
     * Add GROUPBY clause if group attribute was specified on model
     *
     * @param  AdvancedSearch_Model  $model
     * @param  dbCommand        $dbCommand
     * @param  String           $prefix
     */
    private function addGroupBy(AdvancedSearch_Model $model, dbCommand $dbCommand, $prefix)
    {
        $group_by = $this->prefixDbColumn($model->getAttribute('group'), $prefix);

        if (! $group_by) {
            return $dbCommand;
        }

        $dbCommand->group($group_by);
        $model->setCounts($this->calculateGroupedCount($dbCommand));

        return $dbCommand->addSelect("GROUP_CONCAT( DISTINCT elements.id SEPARATOR ',') AS grouped_element_ids");
    }

    /**
     * Add an outer GROUPBY clause so the grouping is the last action performed
     * (for cases where the query contains a HAVING clause, or requires an inner ORDERBY)
     *
     * @param  AdvancedSearch_Model  $model
     * @param  dbCommand        $dbCommand
     * @param  String           $prefix
     */
    private function addOuterGroupBy(AdvancedSearch_Model $model, dbCommand $dbCommand, $prefix)
    {
        $group_by = $this->prefixDbColumn($model->getAttribute('group'), $prefix);

        if (! $group_by) {
            return $dbCommand;
        }

        $original_query = clone($dbCommand);
        $original_query->group('');
        $inner_query = $original_query->getText();
        $original_query->reset()->select("groups.id, count(*) as count, GROUP_CONCAT( DISTINCT groups.id SEPARATOR ',') AS grouped_element_ids FROM ( $inner_query ) AS groups")->group($group_by);

        $model->setCounts($this->calculateGroupedCount($original_query, $dbCommand->params));

        return $original_query;
    }

    /**
     * When grouping search results, count should represent the number of groups.
     * We have to calculate this in a subquery, since the top-level count returns
     * the number of rows in each group, not the total of all groups.
     *
     * @todo : calculate count when paginating but not grouping
     *
     * @param   dbCommand  $dbCommand
     * @param   Array      $params     The sql query params to bind in
     */
    private function calculateGroupedCount(dbCommand $dbCommand, Array $params = [])
    {
        $params = $params ?: $dbCommand->params;

        $count_query = clone($dbCommand);
        $inner_query = $count_query->getText();
        $count_query->reset()->select("count(*) AS count_groups, SUM(count) AS count_items FROM ( $inner_query ) AS all_groups")->bindValues($params);

        return $count_query->queryRow();
    }

    /**
     * Add LIMIT and OFFSET clauses if pagination attributes were specified on the model
     *
     * @param  AdvancedSearch_Model  $model
     * @param  dbCommand        $dbCommand
     */
    private function addPagination(AdvancedSearch_Model $model, dbCommand $dbCommand)
    {
        $per_page = $model->getAttribute('per_page');

        if ($per_page) {
            $dbCommand->limit = $per_page;
            $dbCommand->offset = max($model->getAttribute('page') - 1, 0) * $per_page;
        }

        return $dbCommand;
    }

    /**
     * Execute the query and return the results.
     *
     * If the query included a GROUPBY clause, then return an array of
     * all the found IDs so we can use those to populate the EntryModel.
     *
     * @param  AdvancedSearch_Model  $model
     * @param   dbCommand   $dbCommand
     */
    private function getQueryResult(AdvancedSearch_Model $model, dbCommand $dbCommand)
    {
        $result = $dbCommand->queryAll();

        if ($model->getAttribute('group')) {
            $result = array_reduce($result, function ($carry, $row) {
                return array_merge($carry, $this->csvToArray($row['grouped_element_ids']));
            }, []);
        }

        return $result;
    }

    /**
     * Append table and content prefixes to column names when used in a DbCommand
     *
     * @param   String  $column
     */
    private function prefixDbColumn($column, $prefix)
    {
        if (!$column) { return; }

        return $column == 'title' ? "$prefix.$column" : "$prefix.field_$column";
    }

    /**
     * For debugging, show the criteria array
     *
     * @param   AdvancedSearch_Model  $model
     * @param   boolean          $break
     */
    public function debug(AdvancedSearch_Model $model, $break = false)
    {
        Search::show_criteria($model, $break);
    }

    /**
     * For debugging, dump the constructed SQL statement
     *
     * @param   dbCommand  $query
     */
    private function toSql(dbCommand $query)
    {
        $sql = str_replace(array_keys($query->params), array_values($query->params), $query->getText());
        echo('<pre>');
        Craft::dd($sql);
    }

}
