<?php
/**
 * File: app/Support/Paginator.php
 * Purpose: Defines class Paginator for the app/Support module.
 * Classes:
 *   - Paginator
 * Functions:
 *   - __construct()
 */

namespace Acme\Panel\Support;

class Paginator
{
    public int $page; public int $perPage; public int $total; public int $pages; public array $items;
    public function __construct(array $items,int $total,int $page,int $perPage){
        $this->items=$items; $this->total=$total; $this->perPage=$perPage; $this->page=max(1,$page); $this->pages=$total? (int)ceil($total/$perPage):1;
    }
}

