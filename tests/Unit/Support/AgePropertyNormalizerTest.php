<?php

namespace Tests\Unit\Support;

use App\Support\AgePropertyNormalizer;
use PHPUnit\Framework\TestCase;

class AgePropertyNormalizerTest extends TestCase
{
    private AgePropertyNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new AgePropertyNormalizer;
    }

    public function test_unescapes_addslashes_apostrophes_from_age_driver(): void
    {
        $original = "O'Brien";

        $this->assertSame(
            ['name' => $original],
            $this->normalizer->normalize(['name' => addslashes($original)]),
        );
    }

    public function test_unescapes_multiple_apostrophes(): void
    {
        $original = "it's O'Donnell's";

        $this->assertSame(
            ['name' => $original],
            $this->normalizer->normalize(['name' => addslashes($original)]),
        );
    }

    public function test_unescapes_addslashes_double_quotes(): void
    {
        $original = 'He said "hello"';

        $this->assertSame(
            ['quote' => $original],
            $this->normalizer->normalize(['quote' => addslashes($original)]),
        );
    }

    public function test_does_not_use_stripslashes_on_legitimate_backslashes(): void
    {
        $path = 'C:\\Users\\name';
        $regex = 'hello\\nworld';

        $this->assertSame(
            ['path' => $path, 'regex' => $regex],
            $this->normalizer->normalize(['path' => $path, 'regex' => $regex]),
        );
        $this->assertNotSame('C:Usersname', $this->normalizer->normalize(['path' => $path])['path']);
        $this->assertNotSame("hello\nworld", $this->normalizer->normalize(['regex' => $regex])['regex']);
    }

    public function test_leaves_clean_strings_and_non_strings_unchanged(): void
    {
        $this->assertSame(
            [
                'name' => '李白',
                'year' => 701,
                'ok' => true,
                'score' => 1.5,
                'empty' => null,
            ],
            $this->normalizer->normalize([
                'name' => '李白',
                'year' => 701,
                'ok' => true,
                'score' => 1.5,
                'empty' => null,
            ]),
        );
    }

    public function test_unescapes_nested_enum_list_values(): void
    {
        $this->assertSame(
            ['genre' => ["90's", 'rock']],
            $this->normalizer->normalize(['genre' => [addslashes("90's"), 'rock']]),
        );
    }

    public function test_converts_object_properties_and_rejects_invalid_input(): void
    {
        $this->assertSame(
            ['name' => "O'Brien"],
            $this->normalizer->normalize((object) ['name' => addslashes("O'Brien")]),
        );

        $this->assertSame([], $this->normalizer->normalize(null));
        $this->assertSame([], $this->normalizer->normalize('invalid'));
    }

    public function test_returns_html_special_characters_as_data_for_the_view_layer_to_escape(): void
    {
        $payload = "<script>alert('xss')</script>";

        $this->assertSame(
            ['bio' => $payload],
            $this->normalizer->normalize(['bio' => addslashes($payload)]),
        );
    }
}
