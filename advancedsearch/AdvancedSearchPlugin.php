<?php namespace Craft;

/**
 * Extension of the Element Criteria Model for Advanced search capabilities,
 * without the need to include complex query logic in the template or specify it in a plugin.
 *
 * Supports:
 *     - building complex queries without requiring query logic in template
 *     - searching on external tables, to access records from other plugins
 *     - optimizing query structure based on criteria type
 *     - parsing query strings for search parameters
 *     - mapping query string keys to entry field names
 *     - grouping search results directly in the database query
 *     - paginating results by groups, without breaking groups across pages
 *
 */

class AdvancedSearchPlugin extends BasePlugin
{

    public function getName()
    {
        return 'AdvancedSearch Plugin';
    }

    public function getVersion()
    {
        return '1.0';
    }

    public function getDeveloper()
    {
        return 'Tighten Co./Keith Damiani';
    }

    public function getDeveloperUrl()
    {
        return 'https://github.com/damiani';
    }

}
