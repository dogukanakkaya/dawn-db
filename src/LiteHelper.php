<?php

namespace Codethereal\Database\Sqlite;

class LiteHelper
{
    public static function singularize($str)
    {
        if (substr($str, -1) === 's') {
            return substr($str, 0, -1);
        }
        return $str;
    }
}