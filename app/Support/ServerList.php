<?php
/**
 * File: app/Support/ServerList.php
 * Purpose: Defines class ServerList for the app/Support module.
 * Classes:
 *   - ServerList
 * Functions:
 *   - options()
 *   - valid()
 */

namespace Acme\Panel\Support;








class ServerList
{



    public static function options(): array
    {
        $out=[]; foreach(ServerContext::list() as $id=>$cfg){ $out[]=[ 'id'=>$id, 'label'=>$cfg['name'] ?? __('app.server.default_option', ['id'=>$id]) ]; }
        if(!$out) { $out[]=['id'=>0,'label'=>__('app.support.server_list.default')]; }
        return $out;
    }

    public static function valid(int $id): bool
    { return isset(ServerContext::list()[$id]); }
}

