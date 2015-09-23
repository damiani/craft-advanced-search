# Advanced Search for Craft

> Work in progress...

Extension of the Element Criteria Model for Advanced search capabilities, without the need to include complex query logic in the template or specify it in a plugin.

## Supports:

* building complex queries without requiring query logic in template
* searching on external tables, to access records from other plugins
* optimizing query structure based on criteria type
* parsing query strings for search parameters
* mapping query string keys to entry field names
* grouping search results directly in the database query
* paginating results by groups, without breaking groups across pages
* and some more goodies...

### Syntax example

    {% set criteria = {
        use_query: true,
        section: 'professional',
        group: 'title',
        order: 'sortBy asc',
        page: 2,
        per_page: 10,
        search: {
            'title': {
                field: 'title|subtitle',
            },
            'option 1': {
                field: 'filters',
            },
            'option 2': {
                field: 'filters',
            }
        },
        category: {
            'specialty': {
            },
            'type': {
                field: 'subscriber_type',
            }
        },
        filter: {
            'state': {
                field: 'address.state',
                value: 'CA'
            },
            'zip': {
                field: 'address.zip',
            }
        },
        include: {
            'address': 'smartmap_addresses',
        }
    } %}

    {% set advanced_search = craft.advancedsearch.build(criteria) %}
    {% set entries = advanced_search.find() %}

    Grouped count: {{ advanced_search.count() }}
    Total item count: {{ advanced_search.countItems() }}

    {% set paginate = advanced_search.getPaginator() %}
