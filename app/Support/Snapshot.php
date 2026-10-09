<?php
/**
 * File: app/Support/Snapshot.php
 * Purpose: Defines class Snapshot for the app/Support module.
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
            // 整数先按类型判定，省掉绝大多数值的正则匹配；字符串型数字仍按原样不加引号。
            if(is_int($v) || (is_numeric($v) && preg_match('/^-?\d+$/',(string)$v))){
                $vals[] = (string)$v;
            } else {
                $vals[] = "'".str_replace("'","''", (string)$v)."'";
            }
        }
        return 'INSERT INTO `'.$table.'` ('.$colList.') VALUES ('.implode(',',$vals).');';
    }
}

