<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public static function sameNumber(): array
    {
        return [
            'dash'            => ['0300-1234567'],
            'space'           => ['0300 1234567'],
            'plain'           => ['03001234567'],
            'plus 92'         => ['+92 300 1234567'],
            'plus 92 dashes'  => ['+92-300-1234567'],
            '0092'            => ['00923001234567'],
            'no leading zero' => ['3001234567'],
            'brackets'        => ['(0300) 123-4567'],
        ];
    }

    #[DataProvider('sameNumber')]
    public function test_all_formats_become_the_same_number(string $input): void
    {
        $this->assertSame('03001234567', PhoneNumber::normalize($input));
    }

    public function test_text_with_letters_is_not_changed_so_validation_can_reject_it(): void
    {
        $this->assertSame('abc123', PhoneNumber::normalize('  abc123 '));
    }

    public function test_empty_text_stays_empty(): void
    {
        $this->assertSame('', PhoneNumber::normalize('   '));
    }

    public function test_landline_digits_are_kept_as_digits(): void
    {
        $this->assertSame('0221234567', PhoneNumber::normalize('022-1234567'));
    }
}
