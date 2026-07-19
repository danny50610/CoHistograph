<?php

namespace Tests\Unit\Support;

use App\Support\CypherReservedWords;
use PHPUnit\Framework\TestCase;

class CypherReservedWordsTest extends TestCase
{
    public function test_detects_cypher_reserved_words(): void
    {
        $this->assertTrue(CypherReservedWords::contains('in'));
        $this->assertTrue(CypherReservedWords::contains('MATCH'));
        $this->assertFalse(CypherReservedWords::contains('start_date'));
        $this->assertFalse(CypherReservedWords::contains('perferendis'));
    }
}
