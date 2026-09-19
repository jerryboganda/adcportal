<?php

namespace Tests\Unit;

use App\Support\AgeGroup;
use Tests\TestCase;

/**
 * Age bands exist so a neonatal head ultrasound never receives an adult
 * template. The band derivation must therefore be precise when a date of
 * birth is known and CONSERVATIVE when it is not.
 */
class AgeGroupTest extends TestCase
{
    public function test_month_precision_separates_neonates_from_infants(): void
    {
        $this->assertSame(AgeGroup::NEONATAL, AgeGroup::for(now()->subDays(12)->toDateString(), 0));
        $this->assertSame(AgeGroup::INFANT, AgeGroup::for(now()->subMonths(4)->toDateString(), 0));
        $this->assertSame(AgeGroup::INFANT, AgeGroup::for(now()->subMonths(20)->toDateString(), 1));
        $this->assertSame(AgeGroup::PEDIATRIC, AgeGroup::for(now()->subYears(4)->toDateString(), 4));
    }

    public function test_whole_years_alone_never_claim_the_neonatal_band(): void
    {
        // With only an integer age, a 0- or 1-year-old could be a neonate OR an
        // infant; the less specific (safer) band wins.
        $this->assertSame(AgeGroup::INFANT, AgeGroup::for(null, 0));
        $this->assertSame(AgeGroup::INFANT, AgeGroup::for(null, 1));
    }

    public function test_adult_bands_follow_the_documented_boundaries(): void
    {
        $this->assertSame(AgeGroup::PEDIATRIC, AgeGroup::for(null, 12));
        $this->assertSame(AgeGroup::ADOLESCENT, AgeGroup::for(null, 13));
        $this->assertSame(AgeGroup::ADOLESCENT, AgeGroup::for(null, 17));
        $this->assertSame(AgeGroup::ADULT, AgeGroup::for(null, 18));
        $this->assertSame(AgeGroup::ADULT, AgeGroup::for(null, 64));
        $this->assertSame(AgeGroup::OLDER_ADULT, AgeGroup::for(null, 65));
    }

    public function test_unknown_age_resolves_to_no_band_rather_than_a_guess(): void
    {
        $this->assertNull(AgeGroup::for(null, null));
    }

    public function test_an_unparsable_dob_does_not_throw(): void
    {
        $this->assertSame(AgeGroup::ADULT, AgeGroup::for('not-a-date', 30));
    }

    public function test_labels_and_validation_cover_every_band(): void
    {
        foreach (AgeGroup::all() as $group) {
            $this->assertTrue(AgeGroup::isValid($group));
            $this->assertNotSame('Any age', AgeGroup::label($group));
        }

        $this->assertFalse(AgeGroup::isValid('toddler'));
        $this->assertFalse(AgeGroup::isValid(null));
    }
}
