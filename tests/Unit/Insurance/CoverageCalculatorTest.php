<?php

namespace Tests\Unit\Insurance;

use App\Models\Insurance\InsuranceCoverageRule;
use App\Services\Insurance\CoverageCalculator;
use Tests\TestCase;

/**
 * Unit cover for CoverageCalculator::applyRule().
 *
 * The calculator is pure — we build coverage-rule models in memory and
 * never hit the DB. Exercises the four coverage_type branches plus the
 * max_per_visit cap.
 */
class CoverageCalculatorTest extends TestCase
{
    protected CoverageCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new CoverageCalculator;
    }

    public function test_it_returns_zero_for_null_rule(): void
    {
        $this->assertSame(0.0, $this->calc->applyRule(null, 100.0));
    }

    public function test_it_applies_percentage_coverage(): void
    {
        $rule = new InsuranceCoverageRule([
            'coverage_type' => InsuranceCoverageRule::TYPE_PERCENTAGE,
            'coverage_value' => 80,
            'max_per_visit' => null,
        ]);

        $this->assertEqualsWithDelta(80.0, $this->calc->applyRule($rule, 100.0), 0.001);
    }

    public function test_it_caps_percentage_at_max_per_visit(): void
    {
        $rule = new InsuranceCoverageRule([
            'coverage_type' => InsuranceCoverageRule::TYPE_PERCENTAGE,
            'coverage_value' => 100,
            'max_per_visit' => 50,
        ]);

        // 100% of 200 = 200, but max_per_visit caps it at 50.
        $this->assertEqualsWithDelta(50.0, $this->calc->applyRule($rule, 200.0), 0.001);
    }

    public function test_it_applies_fixed_coverage(): void
    {
        $rule = new InsuranceCoverageRule([
            'coverage_type' => InsuranceCoverageRule::TYPE_FIXED,
            'coverage_value' => 10,
            'max_per_visit' => null,
        ]);

        // Normal case: fixed value below gross.
        $this->assertEqualsWithDelta(10.0, $this->calc->applyRule($rule, 100.0), 0.001);

        // Edge: fixed value exceeds gross → capped at gross.
        $this->assertEqualsWithDelta(5.0, $this->calc->applyRule($rule, 5.0), 0.001);
    }

    public function test_it_applies_copay_amount(): void
    {
        $rule = new InsuranceCoverageRule([
            'coverage_type' => InsuranceCoverageRule::TYPE_COPAY_AMOUNT,
            'coverage_value' => 2,
            'max_per_visit' => null,
        ]);

        // Insurer pays gross - copay: 100 - 2 = 98.
        $this->assertEqualsWithDelta(98.0, $this->calc->applyRule($rule, 100.0), 0.001);

        // Edge: gross is below copay → insurer pays nothing (patient eats it all).
        $this->assertEqualsWithDelta(0.0, $this->calc->applyRule($rule, 1.0), 0.001);
    }
}
