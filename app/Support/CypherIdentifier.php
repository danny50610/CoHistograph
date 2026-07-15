<?php

namespace App\Support;

class CypherIdentifier
{
    /**
     * Quote a Cypher identifier so reserved words (e.g. "in") are safe to use.
     */
    public static function quote(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
}
