<?php

namespace Tests\Unit;

use App\Exceptions\InvalidPhoneException;
use App\Services\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_normalizes_explicit_brazilian_mobile_number_to_e164(): void
    {
        $normalizer = new PhoneNormalizer();

        $this->assertSame('+5511998765432', $normalizer->normalize('+55 (11) 99876-5432'));
        $this->assertSame('5511998765432', $normalizer->digitsOnly('+5511998765432'));
    }

    public function test_does_not_invent_ninth_digit_for_10_digit_brazilian_number(): void
    {
        $normalizer = new PhoneNormalizer();

        // 10-digit Brazilian number with explicit +55 and DDD must keep 10 digits without inventing '9'
        $this->assertSame('+551187654321', $normalizer->normalize('+55 11 8765-4321'));
        $this->assertSame('+552133334444', $normalizer->normalize('+55 (21) 3333-4444'));
    }

    public function test_rejects_brazilian_number_without_ddd_and_does_not_invent_ddd(): void
    {
        $normalizer = new PhoneNormalizer();

        $this->expectException(InvalidPhoneException::class);
        $normalizer->normalize('+55 99876-5432');
    }

    public function test_rejects_number_without_explicit_country_code_when_default_is_null(): void
    {
        $normalizer = new PhoneNormalizer();

        $this->expectException(InvalidPhoneException::class);
        $normalizer->normalize('11998765432');
    }

    public function test_normalizes_national_brazilian_number_with_explicit_country_choice_without_inventing_ddd(): void
    {
        $normalizer = new PhoneNormalizer();

        $this->assertSame('+5511998765432', $normalizer->normalize('(11) 99876-5432', '55'));
        $this->assertSame('+551187654321', $normalizer->normalize('(11) 8765-4321', '55'));
    }

    public function test_rejects_short_or_alphabetic_or_invalid_country_numbers(): void
    {
        $normalizer = new PhoneNormalizer();

        foreach (['abc', '12345', '+0011999999999', '+5500998765432', '+5511098765432', '998765432'] as $invalid) {
            try {
                $normalizer->normalize($invalid, '55');
                $this->fail("Expected InvalidPhoneException for [{$invalid}]");
            } catch (InvalidPhoneException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_masks_phone_number_for_logs(): void
    {
        $normalizer = new PhoneNormalizer();

        $this->assertSame('+5511*****5432', $normalizer->mask('+5511998765432'));
    }
}
