<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\UniqueUserNameGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UniqueUserNameGeneratorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_generates_name_from_email_local_part(): void
    {
        $name = (new UniqueUserNameGenerator)->fromEmail('alice.wonder@gmail.com');

        $this->assertSame('alice.wonder', $name);
    }

    public function test_appends_suffix_when_name_already_taken(): void
    {
        User::factory()->create([
            'name' => 'alice',
        ]);

        $name = (new UniqueUserNameGenerator)->fromEmail('alice@gmail.com');

        $this->assertSame('alice-2', $name);
    }

    public function test_falls_back_to_user_when_local_part_is_empty(): void
    {
        $name = (new UniqueUserNameGenerator)->fromEmail('@gmail.com');

        $this->assertSame('user', $name);
    }

    public function test_increments_suffix_until_unique(): void
    {
        User::factory()->create(['name' => 'bob']);
        User::factory()->create(['name' => 'bob-2']);

        $name = (new UniqueUserNameGenerator)->fromEmail('bob@gmail.com');

        $this->assertSame('bob-3', $name);
    }
}
