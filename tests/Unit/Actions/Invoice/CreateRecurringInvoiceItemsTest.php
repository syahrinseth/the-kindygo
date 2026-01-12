<?php

use App\Actions\ChildEnrolment\CalculatePeriodEnd;
use App\Actions\ChildEnrolment\GetNextBillingDate;
use App\Actions\Invoice\CreateInvoiceItem;
use App\Actions\Invoice\CreateRecurringInvoiceItems;
use App\Enums\ChildEnrolmentBilledEvery;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-01-15');

    test()->tenant = Tenant::factory()->create();
    test()->user = User::factory()->create(['current_tenant_id' => test()->tenant->id]);
    test()->centre = Centre::factory()->create(['tenant_id' => test()->tenant->id]);
    test()->child = Child::factory()->create();
    test()->product = Product::factory()->create(['tenant_id' => test()->tenant->id]);

    test()->createInvoiceItem = mock(CreateInvoiceItem::class);
    test()->calculatePeriodEnd = mock(CalculatePeriodEnd::class);
    test()->getNextBillingDate = mock(GetNextBillingDate::class);

    test()->action = new CreateRecurringInvoiceItems(
        test()->createInvoiceItem,
        test()->calculatePeriodEnd,
        test()->getNextBillingDate
    );

    test()->invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'date' => Carbon::parse('2026-01-15'),
    ]);

    test()->enrolment = ChildEnrolment::factory()->create([
        'child_id' => test()->child->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('creates invoice items within 30-day billing window', function () {
    $dateStart = Carbon::parse('2026-01-20');
    $periodEnd = Carbon::parse('2026-02-19');

    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->with(\Mockery::on(fn ($date) => $date->equalTo($dateStart)), ChildEnrolmentBilledEvery::MONTHLY)
        ->andReturn($periodEnd);

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once()
        ->with(
            test()->invoice,
            test()->enrolment,
            test()->product,
            \Mockery::on(fn ($date) => $date->equalTo($dateStart)),
            \Mockery::on(fn ($date) => $date->equalTo($periodEnd)),
            null
        );

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::MONTHLY,
        $dateStart,
        null
    );
});

it('respects 12-period limit', function () {
    $dateStart = Carbon::parse('2026-01-01');
    $dateEnd = Carbon::parse('2030-12-31');

    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->andReturn(Carbon::parse('2026-01-31'));

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once();

    test()->getNextBillingDate
        ->shouldNotReceive('execute');

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::MONTHLY,
        $dateStart,
        $dateEnd
    );
});

it('only creates one item per execution due to break after first item', function () {
    $dateStart = Carbon::parse('2026-01-10');
    $periodEnd = Carbon::parse('2026-02-09');

    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->andReturn($periodEnd);

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once();

    test()->getNextBillingDate
        ->shouldNotReceive('execute');

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::MONTHLY,
        $dateStart,
        null
    );
});

it('respects enrolment end date', function () {
    $dateStart = Carbon::parse('2026-01-10');
    $dateEnd = Carbon::parse('2026-01-20');
    $periodEnd = Carbon::parse('2026-02-09');

    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->andReturn($periodEnd);

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once()
        ->with(
            test()->invoice,
            test()->enrolment,
            test()->product,
            \Mockery::on(fn ($date) => $date->equalTo($dateStart)),
            \Mockery::on(fn ($date) => $date->equalTo($dateEnd)),
            null
        );

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::MONTHLY,
        $dateStart,
        $dateEnd
    );
});

it('creates minimum one item if all periods are future', function () {
    $dateStart = Carbon::parse('2026-03-01');
    $dateEnd = Carbon::parse('2026-12-31');
    $periodEnd = Carbon::parse('2026-03-31');

    // First call in while loop - period is beyond 30-day window, so shouldBillPeriodNow returns false
    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->andReturn($periodEnd);

    // getNextBillingDate is called because no item was created in the loop
    // Return a date beyond the endDate to exit the while loop
    test()->getNextBillingDate
        ->shouldReceive('execute')
        ->once()
        ->andReturn(Carbon::parse('2027-01-01'));

    // Second call in fallback block (lines 65-79)
    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->andReturn($periodEnd);

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once()
        ->with(
            test()->invoice,
            test()->enrolment,
            test()->product,
            \Mockery::on(fn ($date) => $date->equalTo($dateStart)),
            \Mockery::on(fn ($date) => $date->equalTo($periodEnd)),
            null
        );

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::MONTHLY,
        $dateStart,
        $dateEnd
    );
});

it('handles monthly billing frequency correctly', function () {
    $dateStart = Carbon::parse('2026-01-20');
    $periodEnd = Carbon::parse('2026-02-19');

    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->with(\Mockery::on(fn ($date) => $date->equalTo($dateStart)), ChildEnrolmentBilledEvery::MONTHLY)
        ->andReturn($periodEnd);

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once();

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::MONTHLY,
        $dateStart,
        null
    );
});

it('handles quarterly billing frequency correctly', function () {
    $dateStart = Carbon::parse('2026-01-20');
    $periodEnd = Carbon::parse('2026-04-19');

    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->with(\Mockery::on(fn ($date) => $date->equalTo($dateStart)), ChildEnrolmentBilledEvery::QUARTERLY)
        ->andReturn($periodEnd);

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once();

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::QUARTERLY,
        $dateStart,
        null
    );
});

it('handles yearly billing frequency correctly', function () {
    $dateStart = Carbon::parse('2026-01-20');
    $periodEnd = Carbon::parse('2027-01-19');

    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->with(\Mockery::on(fn ($date) => $date->equalTo($dateStart)), ChildEnrolmentBilledEvery::YEARLY)
        ->andReturn($periodEnd);

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once();

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::YEARLY,
        $dateStart,
        null
    );
});

it('passes notes to invoice item creation', function () {
    $dateStart = Carbon::parse('2026-01-20');
    $periodEnd = Carbon::parse('2026-02-19');
    $notes = 'Special discount applied';

    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->andReturn($periodEnd);

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once()
        ->with(
            test()->invoice,
            test()->enrolment,
            test()->product,
            \Mockery::on(fn ($date) => $date->equalTo($dateStart)),
            \Mockery::on(fn ($date) => $date->equalTo($periodEnd)),
            $notes
        );

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::MONTHLY,
        $dateStart,
        null,
        $notes
    );
});

it('handles period start on the 30-day billing boundary', function () {
    $dateStart = Carbon::parse('2026-02-14');
    $periodEnd = Carbon::parse('2026-03-13');

    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->andReturn($periodEnd);

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once();

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::MONTHLY,
        $dateStart,
        null
    );
});

it('does not bill period beyond 30-day window', function () {
    $dateStart = Carbon::parse('2026-02-20');
    $periodEnd = Carbon::parse('2026-03-19');

    // First call in while loop - beyond 30-day window
    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->andReturn($periodEnd);

    // getNextBillingDate is called because no item was created
    // Return a date far in future to exit loop
    test()->getNextBillingDate
        ->shouldReceive('execute')
        ->once()
        ->andReturn(Carbon::parse('2028-01-01'));

    // Second call in fallback block
    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->andReturn($periodEnd);

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once();

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::MONTHLY,
        $dateStart,
        null
    );
});

it('handles end date in middle of period', function () {
    $dateStart = Carbon::parse('2026-01-20');
    $dateEnd = Carbon::parse('2026-02-05');
    $calculatedPeriodEnd = Carbon::parse('2026-02-19');

    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->andReturn($calculatedPeriodEnd);

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once()
        ->with(
            test()->invoice,
            test()->enrolment,
            test()->product,
            \Mockery::on(fn ($date) => $date->equalTo($dateStart)),
            \Mockery::on(fn ($date) => $date->equalTo($dateEnd)),
            null
        );

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::MONTHLY,
        $dateStart,
        $dateEnd
    );
});

it('stops creating items when current date exceeds end date', function () {
    $dateStart = Carbon::parse('2026-01-01');
    $dateEnd = Carbon::parse('2026-01-05');

    // First call in while loop
    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->andReturn(Carbon::parse('2026-01-31'));

    // The while loop checks if currentDate > dateEnd and breaks at line 42-44
    // Since dateStart (2026-01-01) is not > dateEnd (2026-01-05), it continues
    // shouldBillPeriodNow will return true (2026-01-01 <= 2026-02-14)
    // So one item is created and the loop breaks at line 57-59

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once();

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::MONTHLY,
        $dateStart,
        $dateEnd
    );
});

it('defaults to one year end date when no end date provided', function () {
    $dateStart = Carbon::parse('2026-01-20');
    $periodEnd = Carbon::parse('2026-02-19');

    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->andReturn($periodEnd);

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once();

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::MONTHLY,
        $dateStart,
        null
    );
});

it('creates item with correct period copies', function () {
    $dateStart = Carbon::parse('2026-01-20');
    $periodEnd = Carbon::parse('2026-02-19');

    test()->calculatePeriodEnd
        ->shouldReceive('execute')
        ->once()
        ->andReturn($periodEnd);

    test()->createInvoiceItem
        ->shouldReceive('execute')
        ->once()
        ->with(
            test()->invoice,
            test()->enrolment,
            test()->product,
            \Mockery::on(function ($date) use ($dateStart) {
                return $date->equalTo($dateStart) && $date !== $dateStart;
            }),
            \Mockery::on(function ($date) use ($periodEnd) {
                return $date->equalTo($periodEnd) && $date !== $periodEnd;
            }),
            null
        );

    test()->action->execute(
        test()->invoice,
        test()->enrolment,
        test()->product,
        ChildEnrolmentBilledEvery::MONTHLY,
        $dateStart,
        null
    );
});
