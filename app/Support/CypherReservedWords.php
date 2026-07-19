<?php

namespace App\Support;

class CypherReservedWords
{
    /**
     * openCypher / Apache AGE keywords that break unquoted identifiers.
     *
     * @var list<string>
     */
    private const WORDS = [
        'all',
        'and',
        'as',
        'asc',
        'ascending',
        'by',
        'call',
        'case',
        'contains',
        'count',
        'create',
        'delete',
        'desc',
        'descending',
        'detach',
        'distinct',
        'else',
        'end',
        'ends',
        'exists',
        'false',
        'foreach',
        'in',
        'is',
        'limit',
        'match',
        'merge',
        'not',
        'null',
        'on',
        'optional',
        'or',
        'order',
        'remove',
        'return',
        'set',
        'skip',
        'starts',
        'then',
        'true',
        'union',
        'unique',
        'unwind',
        'when',
        'where',
        'with',
        'xor',
        'yield',
    ];

    public static function contains(string $word): bool
    {
        return in_array(strtolower($word), self::WORDS, true);
    }
}
