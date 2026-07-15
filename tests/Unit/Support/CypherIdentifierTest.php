<?php

namespace Tests\Unit\Support;

use App\Support\CypherIdentifier;
use App\Support\CypherReservedWords;
use PHPUnit\Framework\TestCase;

class CypherIdentifierTest extends TestCase
{
    public function test_quotes_identifier_with_backticks(): void
    {
        $this->assertSame('`in`', CypherIdentifier::quote('in'));
        $this->assertSame('`start_date`', CypherIdentifier::quote('start_date'));
    }

    public function test_escapes_embedded_backticks(): void
    {
        $this->assertSame('`weird``name`', CypherIdentifier::quote('weird`name'));
    }

    public function test_detects_cypher_reserved_words(): void
    {
        $this->assertTrue(CypherReservedWords::contains('in'));
        $this->assertTrue(CypherReservedWords::contains('MATCH'));
        $this->assertFalse(CypherReservedWords::contains('start_date'));
        $this->assertFalse(CypherReservedWords::contains('perferendis'));
    }
}
