<?php namespace Craft;

class AdvancedSearch_PaginatorModel extends BaseModel
{
    public $firstUrl = "p1";
    public $prevUrl;
    public $prevUrls = [];
    public $currentPage;
    public $nextUrl;
    public $nextUrls = [];
    public $lastUrl;
    private $url = '/professional/results/';
    private $prefix = 'p';

    /**
     * @todo : use config for page prefix in querystring and url
     *
     * @param  AdvancedSearch_Model  $search
     */
    public function __construct(AdvancedSearch_Model $search)
    {
        if (!$search->per_page || !$search->count()) {
            return;
        }

        $total_pages = ceil($search->count() / $search->per_page);
        $prev_page = $search->page > 1 ? $search->page - 1 : null;
        $next_page = $search->page < $total_pages ? $search->page + 1 : null;

        $url = $this->url . $this->prefix;

        $this->currentPage = $search->page;
        $this->lastUrl = "{$url}{$total_pages}";

        if ($prev_page) {
            $this->prevUrl = "{$url}{$prev_page}";

            for ($i = 1; $i < $this->currentPage; $i++) {
                $this->prevUrls[$i] = "{$url}{$i}";
            }
        }

        if ($next_page) {
            $this->nextUrl = "{$url}{$next_page}";

            for ($i = $next_page; $i <= $total_pages; $i++) {
                $this->nextUrls[$i] = "{$url}{$i}";
            }
        }
    }

    public function getPrevUrls()
    {
        return $this->prevUrls;
    }

    public function getNextUrls()
    {
        return $this->nextUrls;
    }
}
