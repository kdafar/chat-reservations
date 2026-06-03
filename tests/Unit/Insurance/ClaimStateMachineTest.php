<?php

namespace Tests\Unit\Insurance;

use App\Models\Insurance\InsuranceClaim;
use App\Services\Insurance\ClaimStateMachine;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Unit cover for the declarative claim state machine.
 *
 * No DB required — the state machine is pure logic over the TRANSITIONS
 * constant. Tests guard against transition regressions that would let the
 * service layer push a claim into an illegal state.
 */
class ClaimStateMachineTest extends TestCase
{
    protected ClaimStateMachine $sm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sm = new ClaimStateMachine;
    }

    public function test_it_allows_draft_to_submitted(): void
    {
        $this->assertTrue($this->sm->canTransition(
            InsuranceClaim::STATUS_DRAFT,
            InsuranceClaim::STATUS_SUBMITTED
        ));

        // assertTransition must not throw on a legal transition.
        $this->sm->assertTransition(
            InsuranceClaim::STATUS_DRAFT,
            InsuranceClaim::STATUS_SUBMITTED
        );
        $this->addToAssertionCount(1);
    }

    public function test_it_disallows_draft_to_paid(): void
    {
        $this->assertFalse($this->sm->canTransition(
            InsuranceClaim::STATUS_DRAFT,
            InsuranceClaim::STATUS_PAID
        ));

        $this->expectException(InvalidArgumentException::class);
        $this->sm->assertTransition(
            InsuranceClaim::STATUS_DRAFT,
            InsuranceClaim::STATUS_PAID
        );
    }

    public function test_it_allows_submitted_to_approved(): void
    {
        $this->assertTrue($this->sm->canTransition(
            InsuranceClaim::STATUS_SUBMITTED,
            InsuranceClaim::STATUS_APPROVED
        ));
    }

    public function test_it_allows_approved_to_paid(): void
    {
        $this->assertTrue($this->sm->canTransition(
            InsuranceClaim::STATUS_APPROVED,
            InsuranceClaim::STATUS_PAID
        ));
    }

    public function test_it_disallows_rejected_to_approved(): void
    {
        // Rejected is a sink state — only void should be reachable from it.
        $this->assertFalse($this->sm->canTransition(
            InsuranceClaim::STATUS_REJECTED,
            InsuranceClaim::STATUS_APPROVED
        ));

        $allowed = $this->sm->allowedNextStates(InsuranceClaim::STATUS_REJECTED);
        $this->assertSame([InsuranceClaim::STATUS_VOID], $allowed);

        $this->expectException(InvalidArgumentException::class);
        $this->sm->assertTransition(
            InsuranceClaim::STATUS_REJECTED,
            InsuranceClaim::STATUS_APPROVED
        );
    }

    public function test_it_returns_allowed_next_states_for_draft(): void
    {
        $allowed = $this->sm->allowedNextStates(InsuranceClaim::STATUS_DRAFT);

        $this->assertContains(InsuranceClaim::STATUS_SUBMITTED, $allowed);
        $this->assertContains(InsuranceClaim::STATUS_VOID, $allowed);
    }

    public function test_it_returns_empty_array_for_void_state(): void
    {
        // Void is terminal — no further transitions out.
        $this->assertSame([], $this->sm->allowedNextStates(InsuranceClaim::STATUS_VOID));
    }
}
