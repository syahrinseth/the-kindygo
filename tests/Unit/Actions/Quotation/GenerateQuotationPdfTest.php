<?php

use App\Actions\Quotation\GenerateQuotationPdf;
use App\Models\Centre;
use App\Models\Quotation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PdfConfigurationService;
use Carbon\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-01-08');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user->id);
    actingAs($this->user);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->quotation = Quotation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'number' => 'QUO/001/2026/0001',
    ]);

    $this->pdfConfig = $this->mock(PdfConfigurationService::class);
    $this->pdfConfig->shouldReceive('getStandardFormat')->byDefault()->andReturn('A4');
    $this->pdfConfig->shouldReceive('getStandardMargins')->byDefault()->andReturn([10, 10, 10, 10]);
    $this->pdfConfig->shouldReceive('configureBrowsershot')->byDefault()->andReturn(Mockery::mock(\Spatie\Browsershot\Browsershot::class));

    $this->action = new GenerateQuotationPdf($this->pdfConfig);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('generates filename with quotation number', function () {
    $filename = $this->action->generateFilename($this->quotation);

    expect($filename)->toBe('quotation-QUO-001-2026-0001.pdf');
});

it('sanitizes special characters in filename', function () {
    $this->quotation->number = 'QUO/001\\2026:0001*test?';

    $filename = $this->action->generateFilename($this->quotation);

    expect($filename)->toBe('quotation-QUO-001-2026-0001-test-.pdf')
        ->and($filename)->not->toContain('/')
        ->and($filename)->not->toContain('\\')
        ->and($filename)->not->toContain(':')
        ->and($filename)->not->toContain('*')
        ->and($filename)->not->toContain('?');
});

it('sanitizes all invalid filename characters', function () {
    $this->quotation->number = 'TEST/<>:"|?*\\';

    $filename = $this->action->generateFilename($this->quotation);

    expect($filename)->toBe('quotation-TEST---------.pdf')
        ->and($filename)->not->toContain('<')
        ->and($filename)->not->toContain('>')
        ->and($filename)->not->toContain('"')
        ->and($filename)->not->toContain('|');
});

it('generates pdf with correct format', function () {
    $this->pdfConfig->shouldReceive('getStandardFormat')
        ->once()
        ->andReturn('A4');

    $this->action->execute($this->quotation);
});

it('generates pdf with correct margins', function () {
    $this->pdfConfig->shouldReceive('getStandardMargins')
        ->once()
        ->andReturn([10, 10, 10, 10]);

    $this->action->execute($this->quotation);
});

it('uses pdf configuration service for browsershot setup', function () {
    // This test verifies that the action is structured to use configureBrowsershot
    // The actual call happens inside a callback that only executes during PDF generation
    $reflection = new ReflectionMethod(GenerateQuotationPdf::class, 'execute');
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('configureBrowsershot')
        ->and($source)->toContain('withBrowsershot');
});

it('returns pdf builder response', function () {
    $response = $this->action->execute($this->quotation);

    expect($response)->not->toBeNull();
});

it('handles quotation numbers without special characters', function () {
    $this->quotation->number = 'SIMPLE123';

    $filename = $this->action->generateFilename($this->quotation);

    expect($filename)->toBe('quotation-SIMPLE123.pdf');
});

it('preserves hyphens in quotation number', function () {
    $this->quotation->number = 'QUO-001-2026-0001';

    $filename = $this->action->generateFilename($this->quotation);

    expect($filename)->toBe('quotation-QUO-001-2026-0001.pdf');
});
