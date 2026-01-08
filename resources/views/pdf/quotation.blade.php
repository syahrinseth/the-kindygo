<style>
	* {
		font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;
		box-sizing: border-box;
	}

	body {
		color: #333;
		margin: 0;
		padding: 0;
	}

	table {
		border-collapse: collapse;
		mso-table-lspace: 0;
		mso-table-rspace: 0;
	}

	table tr td, table tr th {
		padding: 0;
	}

	@page {
		size: 210mm 297mm;
		margin: 10mm 0mm;
		background: #fff;
	}

	.paper-size {
		background: #fff;
		margin: 0 auto;
		padding: 10mm;
		max-width: 210mm;
	}

	.header-shade {
		background: #e2e8f0;
	}

	.brand-logo>img {
		max-width: 200px;
		max-height: 100px;
	}

	.brand-company {
		width: 40%;
		padding-right: 2rem;
	}

	.brand-label {
		font-size: 1.5rem;
		font-weight: bold;
	}

	.address-fieldset {
		border: solid 1px #e5e5e5;
		padding-top: 0.5rem;
	}

	.address-fieldset legend {
		padding: 0 4px;
		font-size: 0.75em;
		color: #aaa;
		letter-spacing: 0.1rem;
	}

	.address-details, .address-client {
		padding: 0rem 3rem 1rem 0;
		line-height: 1.5em;
	}

	.address-name {
		font-weight: bold;
		text-transform: uppercase;
		padding-bottom: 4px;
	}

	.invoice-table {
		font-size: 0.8rem;
		border-color: #e5e5e5;
		line-height: 1.5;
	}

	.invoice-table th, .invoice-table td {
		padding: 0.5rem 0.5rem;
	}

	.invoice-table .thead {
		text-transform: uppercase;
		font-size: 0.7rem;
		border-bottom: solid 2px #e2e8f0;
		background: #f1f5f9;
	}

	.total-body {
		border-top: solid 1px #e2e8f0;
		background: #f1f5f9;
	}

	.align-left { text-align: left; }
	.align-right { text-align: right; }
	.align-center { text-align: center; }

	.status-badge {
		display: inline-block;
		padding: 0.25rem 0.75rem;
		border-radius: 4px;
		font-size: 0.75rem;
		font-weight: bold;
		text-transform: uppercase;
	}

	.status-draft { background: #e5e5e5; color: #666; }
	.status-pending { background: #fef3c7; color: #92400e; }
	.status-accepted { background: #d1fae5; color: #065f46; }
	.status-converted { background: #dbeafe; color: #1e40af; }
	.status-expired { background: #fee2e2; color: #991b1b; }
	.status-rejected { background: #fee2e2; color: #991b1b; }
</style>

<div class="paper-size header-shade">
	<table width="100%">
		<tr>
			<td class="invoice-header">
				<table width="100%">
					<tr>
						<td class="brand-logo" style="padding-right: 3rem;">
							<img src="{{ asset('/images/loe.png') }}" alt="KindyGo">
						</td>
						<td class="brand-company">
							<div class="brand-label">QUOTATION</div>
							<div class="company-title">
								<div>{{ $quotation->tenant->name ?? 'KindyGo' }}</div>
								@if($quotation->tenant && $quotation->tenant->business_registration_number)
									<div>({{ $quotation->tenant->business_registration_number }})</div>
								@endif
							</div>
						</td>
						<td style="text-align: right; width: 50%; padding-left: 2rem; line-height: 1.3em; font-size: 0.75rem; color: #64748b;">
							<div><strong>{{ $quotation->centre->name }}</strong></div>
							@if($quotation->centre->address_1)
								<div>{{ $quotation->centre->address_1 }}</div>
							@endif
							@if($quotation->centre->address_2)
								<div>{{ $quotation->centre->address_2 }}</div>
							@endif
							<div>
								{{ $quotation->centre->postal_code }} {{ $quotation->centre->city }}, {{ $quotation->centre->state }}
							</div>
							@if($quotation->centre->phone)
								<div>Tel: {{ $quotation->centre->phone }}</div>
							@endif
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>

<div class="paper-size">
	<div>
		<table width="100%">
			<tr>
				<td style="padding-bottom: 2rem;">
					<table width="100%">
						<tr>
							<td style="width: 50%; padding-right: 2rem;">
								<strong>Quotation: {{ $quotation->number }}</strong>
							</td>
							<td style="width: 50%; text-align: right;">
								<span class="status-badge status-{{ strtolower($quotation->status->value) }}">
									{{ $quotation->status->label() }}
								</span>
							</td>
						</tr>
						<tr>
							<td style="padding-top: 0.5rem;">
								<small>Date: {{ $quotation->date->format('M d, Y') }}</small>
							</td>
							<td style="text-align: right; padding-top: 0.5rem;">
								<small style="@if($quotation->isExpired()) color: #991b1b; font-weight: bold; @endif">
									Valid Until: {{ $quotation->valid_until->format('M d, Y') }}
									@if($quotation->isExpired())
										(EXPIRED)
									@endif
								</small>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>

		<div class="address-fieldset" style="margin-bottom: 1rem;">
			<table width="100%">
				<tr>
					<td class="address-details" style="width: 50%; border-right: solid 1px #e5e5e5;">
						<legend>FROM</legend>
						<div class="address-name">{{ $quotation->tenant->name ?? 'KindyGo' }}</div>
						@if($quotation->tenant && $quotation->tenant->business_registration_number)
							<div><small>{{ $quotation->tenant->business_registration_number }}</small></div>
						@endif
					</td>
					<td class="address-client" style="width: 50%; padding-left: 3rem;">
						<legend>TO</legend>
						<div class="address-name">{{ $quotation->user->name }}</div>
						@if($quotation->child)
							<div><small>For: {{ $quotation->child->full_name }}</small></div>
						@endif
						@if($quotation->user->email)
							<div><small>{{ $quotation->user->email }}</small></div>
						@endif
						@if($quotation->user->profile && $quotation->user->profile->phone)
							<div><small>{{ $quotation->user->profile->phone }}</small></div>
						@endif
					</td>
				</tr>
			</table>
		</div>

		<div style="padding-top: 2rem;">
			<table class="invoice-table" width="100%">
				<tr class="thead">
					<th class="align-left">Name</th>
					<th class="align-center" width="8%">Qty</th>
					<th class="align-right" width="13%">Price (RM)</th>
					<th class="align-right" width="13%">Discount (RM)</th>
					<th class="align-right" width="16%">Subtotal (RM)</th>
				</tr>
				<tbody>
					@forelse ($quotation->quotationItems as $item)
						<tr class="order-item">
							<td>
								<strong>{{ $item->name }}</strong>
								@if($item->description)
									<br><small>{{ $item->description }}</small>
								@endif
								@if($item->child)
									<br><small>Child: {{ $item->child->full_name }}</small>
								@endif
							</td>
							<td class="align-center">{{ $item->quantity }}</td>
							<td class="align-right">{{ number_format($item->price / 100, 2) }}</td>
							<td class="align-right">{{ number_format($item->discount / 100, 2) }}</td>
							<td class="align-right">{{ number_format($item->total / 100, 2) }}</td>
						</tr>
					@empty
						<tr>
							<td colspan="5" class="align-center"><em>No items found</em></td>
						</tr>
					@endforelse
				</tbody>

				<tbody class="total-body">
					<tr>
						<td colspan="4" class="align-right"><strong>SUBTOTAL</strong></td>
						<td class="align-right"><strong>{{ number_format($quotation->total_amount / 100, 2) }}</strong></td>
					</tr>
					<tr>
						<td colspan="4" class="align-right"><strong>TOTAL DISCOUNT</strong></td>
						<td class="align-right"><strong>-{{ number_format(($quotation->total_amount - $quotation->total) / 100, 2) }}</strong></td>
					</tr>
					<tr>
						<td colspan="4" class="align-right"><strong>TOTAL DUE</strong></td>
						<td class="align-right"><strong>{{ number_format($quotation->total / 100, 2) }}</strong></td>
					</tr>
				</tbody>
			</table>
		</div>

		@if($quotation->notes)
			<div style="padding-top: 2rem;">
				<strong>Notes:</strong>
				<div style="padding-top: 0.5rem; font-size: 0.9rem;">{{ $quotation->notes }}</div>
			</div>
		@endif

		@if($quotation->terms_conditions)
			<div style="padding-top: 2rem;">
				<strong>Terms & Conditions:</strong>
				<div style="padding-top: 0.5rem; font-size: 0.9rem;">{{ $quotation->terms_conditions }}</div>
			</div>
		@endif
	</div>
</div>

<div class="paper-size">
	<div class="footer align-center">
		<small>{{ $quotation->tenant->name ?? 'KindyGo' }} Quotation System</small>
	</div>
</div>
