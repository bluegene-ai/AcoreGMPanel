<?php
/**
 * File: app/Support/Snapshot.php
 * Purpose: Defines class Snapshot for the app/Support module.
 * Classes:
 *   - Snapshot
 * Functions:
 *   - buildInsert()
 */

namespace Acme\Panel\Support;




class Snapshot
{
    public static function buildInsert(string $table, array $row): string
    {
        if(!$row) return '';
        $cols = array_keys($row);
        $colList = '`'.implode('`,`',$cols).'`';
        $vals = [];
        foreach($row as $k=>$v){
            if($v === null){ $vals[] = 'NULL'; continue; }
            if(is_numeric($v) && preg_match('/^-?\d+$/',(string)$v)){
                $vals[] = (string)$v;
            } else {
                $vals[] = "'".str_replace("'","''", (string)$v)."'";
            }
        }
        return 'INSERT INTO `'.$table.'` ('.$colList.') VALUES ('.implode(',',$vals).');';
    }
}

