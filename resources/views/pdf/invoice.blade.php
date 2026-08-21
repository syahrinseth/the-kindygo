<style>
	/* General CSS normalisation - investigating common CSS seen in emails */
	/*
Normalise on all email clients
Apple Mail, iOS Mail plus many more have preset margin and padding for the email body - this normalises it so rendering is consistent and designers can choose.
*/
	body {
		margin: 0;
		padding: 0;
	}

	/*
Fix for Outlook on Windows
border-collapse to stop spaces between tables caused by border size
mso-table-lspace / mso-table-rspace to ensure no left and right space is added next to tables - Outlook specific CSS attributes
	*/
	table {
		border-collapse: collapse;
		mso-table-lspace: 0;
		mso-table-rspace: 0;
	}

	/*
Older versions of Samsung mail reset the font-size on <h1>-<h6> elements - But the newer versions don’t.
Mail.ru resets font-size on <h1> & <h3> but other <h*> are left
outlook.com resets margin on an <h3> but others are left
So I think a “normalise” on <h1>-<h3> would make sense
*/
	h1 {
		margin: 0.67em 0;
		font-size: 2em;
	}

	h2 {
		margin: 0.83em 0;
		font-size: 1.5em;
	}

	/* html[dir] h3 is to increase specificity to override Outlook.com */
	html[dir] h3,
	h3 {
		margin: 1em 0;
		font-size: 1.17em;
	}

	/* From here - all CSS normalisation is based on a specific email client sitution */
	/* Fix for Outlook links color fix for links and visited links */
	span.MsoHyperlink {
		color: inherit !important;
		mso-style-priority: 99 !important;
	}

	span.MsoHyperlinkFollowed {
		color: inherit !important;
		mso-style-priority: 99 !important;
	}

	/* normalise link attributes in Apple Mail / iOS Mail apps - to match the parent element */
	#root [x-apple-data-detectors=true],
	a[x-apple-data-detectors=true] {
		color: inherit !important;
		text-decoration: inherit !important;
	}

	/* normalise link attributes in Gmail - to match the parent element. NOTE: Need to add class="body" to the body element and a DOCTYPE must be present. */
	u+.body a {
		color: inherit;
		text-decoration: none;
		font-size: inherit;
		font-weight: inherit;
		line-height: inherit;
	}

	/* Mark Robbins found iOS Gmail will add word-spacing: 1px and word-wrap: break-word
https://github.com/JayOram/email-css-resets/issues/2#issue-805476023
so added the below to fix that

This doesn't fix GANGA - so may need to be added inline -
<body style="word-wrap: normal; word-spacing:normal;">
*/
	.body {
		word-wrap: normal;
		word-spacing: normal;
	}

	/* centre email on Android 4.4 - margin reset */
	div[style*="margin: 16px 0"] {
		margin: 0 !important;
	}

	/***/
	/* Kindygo EMAIL / PDF CSS */
	/***/
	* {
		font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;
		box-sizing: border-box;
	}

	body {
		color: #333;
	}

	table tr td,
	table tr th {
		padding: 0;
	}

	small {
		color: #666;
	}

	@page {
		size: 210mm 297mm;
		margin: 10mm 0mm;
		background: #fff;
	}

	@page :first {
		margin-top: 0;
	}

	.paper-size {
		background: #fff;
		margin: 0 auto;
		padding: 10mm;
		max-width: 210mm;
		/*height: 297mm;*/
	}

	.header-shade {
		background: #e2e8f0;
	}

	.invoice-address {
		font-size: 0.8rem;
	}

	.brand-logo {
		padding-right: 3rem;
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
		/* letter-spacing: 1mm; */
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

	.address-branch {
		text-align: right;
		width: 50%;
		padding-left: 2rem;
		line-height: 1.3em;
		font-size: 0.75rem;
		color: #64748b;
	}

	.address-details {
		/*border-right: solid 1px #e5e5e5;*/
		padding: 0rem 3rem 1rem 0;
		line-height: 1.5em;
	}

	.address-client {
		text-align: right;
		width: 50%;
		padding: 0rem 0 1rem 3rem;
		line-height: 1.5em;
	}

	.address-name {
		font-weight: bold;
		text-transform: uppercase;
		padding-bottom: 4px;
	}

	.link {
		color: inherit;
	}

	.order-details th {
		padding-left: 1rem;
	}

	.invoice-table-row {
		padding-top: 3rem !important;
	}

	.align-left {
		text-align: left;
	}

	.align-right {
		text-align: right;
	}

	.align-center {
		text-align: center;
	}

	.invoice-table {
		page-break-inside: always;
		font-size: 0.8rem;
		border-color: #e5e5e5;
		line-height: 1.5;
	}

	.invoice-table tr {
		page-break-inside: avoid;
		page-break-before: auto;
		page-break-after: auto;
	}

	.invoice-table th,
	.invoice-table td {
		padding: 0.5rem 0.5rem;
	}

	.invoice-table .thead {
		text-transform: uppercase;
		font-size: 0.7rem;
		border-bottom: solid 2px #e2e8f0;
		background: #f1f5f9;
		white-space: nowrap;
	}

	.invoice-table tbody td {
		vertical-align: top;
	}

	.total-body {
		border-top: solid 1px #e2e8f0;
		background: #f1f5f9;
	}

	.payment-body {
		border-top: solid 1px #e2e8f0;
	}

	.invoice-table tfoot {
		border-top: solid 1px #e2e8f0;
		border-bottom: solid 1px #e2e8f0;
	}

	.btn-pay-now {
		display: inline-block;
		border-radius: 4px;
		padding: 0.5rem 1rem;
		color: #fff;
		background: #0ea5e9;
		text-decoration: none;
	}




</style>

<div class="paper-size header-shade">
	<table width="100%">
		<tr>
			<td class="invoice-header">
				{{-- INVOICE HEADER --}}
				<table width="100%">
					<tr>
						<td class="brand-logo">
							<img src="{{ asset('/images/loe.png') }}" alt="KindyGo">
						</td>
						<td class="brand-company">
							<div class="brand-label">
								INVOICE
							</div>
							<div class="company-title">
								<div>{{ $invoice->tenant->name ?? 'KindyGo' }}</div>
								@if($invoice->tenant && $invoice->tenant->business_registration_number)
									<div>({{ $invoice->tenant->business_registration_number }})</div>
								@endif
							</div>
						</td>
						<td class="address-branch">
							@if ($invoice->centre)
								<div class="address-name">{{ $invoice->centre->name }}</div>
								@if($invoice->centre->full_address)
									<div>{{ $invoice->centre->full_address }}</div>
								@endif
								@if($invoice->centre->phone)
									<div>{{ $invoice->centre->phone }}</div>
								@endif
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
		<div>
			{{-- INVOICE FROM & TO --}}
			<table width="100%" class="invoice-address">
				<tr>
					<td class="address-details" valign="top">
						<div class="address-name">
							<div style="font-size: 1.1rem; margin-right: 10px;">Invoice ID: {{ $invoice->number }}</div>
							@if($invoice->status)
								@if($invoice->status->value == 'overdue')
									<span style="color: #ffffff; font-weight: bold; background-color: #dc2626; padding: 4px 8px; border-radius: 4px; display: inline-block; text-transform: uppercase; border: 2px solid #dc2626;">{{ $invoice->status->value }}</span>
								@elseif($invoice->status->value == 'pending')
									<span style="color: #ffffff; font-weight: bold; background-color: #6b7280; padding: 4px 8px; border-radius: 4px; display: inline-block; text-transform: uppercase; border: 2px solid #6b7280;">{{ $invoice->status->value }}</span>
								@else
									<span style="color: #ffffff; font-weight: bold; background-color: #16a34a; padding: 4px 8px; border-radius: 4px; display: inline-block; text-transform: uppercase; border: 2px solid #16a34a;">{{ $invoice->status->value }}</span>
								@endif
							@endif
						</div>
						<div>
							<table class="order-details">
								<tbody>
									<tr class="order-number">
										<td nowrap="" align="left">
											Billing For:
										</td>
										<th nowrap="" class="align-right">
											{{ $invoice->date->format('F Y') }}
										</th>
									</tr>
									<tr class="order-number">
										<td nowrap="" align="left">
											Payment Due:
										</td>
										<th nowrap="" class="align-right" style="color: #dc2626;">
											{{ $invoice->due_at->format('d M Y') }}
										</th>
									</tr>
									<tr class="invoice-date">
										<td nowrap="">
											Invoice Date:
										</td>
										<th nowrap="" class="align-right">
											{{ $invoice->date->format('d M Y') }}
										</th>
									</tr>
								</tbody>
							</table>
						</div>
					</td>
					<td class="address-client" valign="top">
						@if ($invoice->user)
							<div class="address-name">{{ strtoupper($invoice->user->name) }}</div>
							@if($invoice->user->email)
								{{ $invoice->user->email }}<br>
							@endif
							@if($invoice->user->phone)
								{{ $invoice->user->phone }}<br>
							@endif
						@endif
					</td>
				</tr>
			</table>
		</div>
		<div class="invoice-table-row">
			{{-- INVOICE TABLE --}}
			<table width="100%" class="invoice-table" border="0">
				<tr class="thead">
					<th class="align-left">
						Items
					</th>
					<th width="8%">
						Qty
					</th>
					<th class="align-right" width="13%">
						Price (RM)
					</th>
					<th class="align-right" width="13%">
						Discount (RM)
					</th>
					<th class="align-right" width="16%">
						Subtotal (RM)
					</th>
				</tr>
				<tbody>
					@forelse ($invoice->invoiceItems as $item)
						<tr class="order-item">
							<td>
								<strong class="word">{{ $item->name }}</strong>
								@if($item->child && $item->child->name)
									<br>
									<small class="word">
										<div>Child: {{ $item->child->name }}</div>
									</small>
								@endif
							</td>
							<td class="align-center">
								<span>{{ $item->quantity }}</span>
							</td>
							<td class="align-right">
								{{ number_format($item->price / 100, 2) }}
							</td>
							<td class="align-right">
								{{ number_format($item->discount / 100, 2) }}
							</td>
							<td class="align-right">
								{{ number_format($item->total / 100, 2) }}
							</td>
						</tr>
					@empty
						<tr class="order-item">
							<td colspan="5" class="align-center">
								<em>No items found</em>
							</td>
						</tr>
					@endforelse
				</tbody>

				@if($invoice->payments->count() > 0)
					<tr class="thead">
						<th class="align-left">
							Payments
						</th>
						<th width="8%"></th>
						<th class="align-right" width="13%"></th>
						<th class="align-right" width="13%"></th>
						<th class="align-right" width="16%"></th>
					</tr>
					<tbody>
						@foreach ($invoice->payments as $payment)
							<tr class="order-item">
								<td>
									<strong class="word">
										Payment - {{ $payment->gateway ? ucfirst($payment->gateway->value) : 'N/A' }}
									</strong>
									@if($payment->description)
										<br>
										<small class="word">{{ $payment->description }}</small>
									@endif
									@if($payment->paid_at)
										<br>
										<small class="word">Paid on: {{ $payment->paid_at->format('d M Y H:i') }}</small>
									@endif
								</td>
								<td class="align-center">
									<span></span>
								</td>
								<td class="align-right"></td>
								<td class="align-right"></td>
								<td class="align-right">
									-{{ number_format($payment->pivot->amount / 100, 2) }}
								</td>
							</tr>
						@endforeach
					</tbody>
				@endif

				<tbody class="total-body">
					<tr>
						<td colspan="4" class="align-right">
							<strong>SUBTOTAL</strong>
						</td>
						<td class="align-right">
							<strong>{{ number_format($invoice->total_amount / 100, 2) }}</strong>
						</td>
					</tr>
					@if($invoice->total_discounts > 0)
						<tr>
							<td colspan="4" class="align-right">
								<strong>TOTAL DISCOUNT</strong>
							</td>
							<td class="align-right">
								<strong>-{{ number_format($invoice->total_discounts / 100, 2) }}</strong>
							</td>
						</tr>
					@endif
					<tr>
						<td colspan="4" class="align-right">
							<strong>TOTAL DUE</strong>
						</td>
						<td class="align-right">
							<strong>{{ number_format($invoice->total_amount / 100, 2) }}</strong>
						</td>
					</tr>
					@if($invoice->payments->count() > 0)
						<tr>
							<td colspan="4" class="align-right">
								<strong>TOTAL PAID</strong>
							</td>
							<td class="align-right">
								<strong>-{{ number_format($invoice->payments->sum('pivot.amount') / 100, 2) }}</strong>
							</td>
						</tr>
						<tr>
							<td colspan="4" class="align-right">
								<strong>BALANCE DUE</strong>
							</td>
							<td class="align-right">
							<strong>{{ number_format(($invoice->total_amount - $invoice->payments->sum('pivot.amount')) / 100, 2) }}</strong>
							</td>
						</tr>
					@endif
				</tbody>
			</table>
		</div>
		<div>&nbsp;</div>
		@if($invoice->status->value !== 'paid')
			<div class="align-right">
				<a href="{{ route('invoice.download-pdf', $invoice) }}" style="display: inline-block; background-color: #0ea5e9; color: white; font-weight: bold; padding: 8px 16px; border-radius: 4px; text-decoration: none;">VIEW INVOICE</a>
			</div>
		@endif
	</div>
</div>
<div class="paper-size">
	<div class="footer align-center">
		<small>{{ $invoice->tenant->name ?? 'KindyGo' }} Invoice System</small>
	</div>
</div>
