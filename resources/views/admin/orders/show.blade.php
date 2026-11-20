@extends('admin.layouts.app')
@use('App\Support\OrderLifecycle')

@section('title', $order->order_number)

@section('content')
  @php
    $po = $order->providerOrder;
    $latest = $order->payments->last();
    $fmt = fn ($at) => $at?->format('d M Y, H:i:s');
  @endphp

  <div class="adm-head">
    <div>
      <h1 class="adm-code-title"><span class="adm-code">{{ $order->order_number }}</span></h1>
      <p class="adm-sub"><a href="{{ route('admin.orders.index') }}">&larr; Orders &amp; fulfillment</a> · Statuses change only through payment verification and the fulfilment service</p>
    </div>
    <div class="adm-actions">
      <span class="adm-badge adm-badge-{{ OrderLifecycle::orderTone($order->payment_status) }}">{{ OrderLifecycle::orderLabel($order->payment_status) }}</span>
      <span class="adm-badge adm-badge-{{ OrderLifecycle::stageTone($stage) }}">{{ OrderLifecycle::stageLabel($stage) }}</span>
    </div>
  </div>

  @if (in_array($stage, ['failed', 'needs_attention', 'needs_reconciliation'], true))
    <div class="adm-alert {{ $stage === 'needs_attention' ? 'adm-alert-warn' : 'adm-alert-error' }}" role="status">
      @if ($stage === 'needs_reconciliation')
        <strong>The eSIM Go purchase may or may not have gone through.</strong>
        It will never be re-sent automatically. Check the eSIM Go account for this order before doing anything else.
      @elseif ($stage === 'failed')
        <strong>eSIM Go refused the purchase — nothing was bought.</strong>
        After fixing the cause (for example the account balance), use “Retry failed purchase” below, or run on the server:
        <span class="adm-code">php artisan esimgo:fulfil {{ $order->order_number }} --retry-failed</span>
      @else
        <strong>This order needs attention.</strong>
        @if ($po?->provider_reference)
          eSIM Go order {{ $po->provider_reference }} exists; fetching the eSIM details again (“Reconcile now” below) never buys again:
          <span class="adm-code">php artisan esimgo:fulfil {{ $order->order_number }}</span>
        @endif
      @endif
      @if ($po?->last_error)
        <small class="adm-cell-sub">Last error: {{ $po->last_error }}</small>
      @endif
    </div>
  @endif

  <div class="adm-card">
    <h2>Lifecycle</h2>
    <ol class="adm-timeline">
      @foreach ($timeline as $step)
        <li class="adm-timeline-step is-{{ $step['state'] }}">
          <span class="adm-timeline-dot" aria-hidden="true"></span>
          <div>
            <strong>{{ $step['label'] }}</strong>
            <span class="adm-visually-hidden">({{ $step['state'] }})</span>
            <small class="adm-cell-sub">{{ $step['detail'] }}@if ($step['at']) · {{ $fmt($step['at']) }}@endif</small>
          </div>
        </li>
      @endforeach
    </ol>
  </div>

  {{-- Phase 9F: administrative actions. Availability is decided on the server; every request is re-checked and audited. --}}
  <section class="adm-card" aria-labelledby="actions-title">
    <div class="adm-card-head">
      <h2 id="actions-title">Administrative actions</h2>
      <span class="adm-hint">No action here can cancel, mark paid or mark fulfilled.</span>
    </div>
    <div class="adm-grid adm-grid-2 adm-order-actions">
      <div class="adm-order-action">
        <h3>Reconcile with eSIM Go</h3>
        <p class="adm-hint">Looks up the existing eSIM Go order by its stored reference (read-only) and stores any eSIMs it returns. It never sends a purchase.</p>
        @if ($reconcileBlocked)
          <p class="adm-action-why"><span class="adm-badge">Unavailable</span> {{ $reconcileBlocked }}</p>
        @else
          <p class="adm-action-why"><span class="adm-badge adm-badge-info">Available</span> eSIM Go order <span class="adm-code">{{ $po->provider_reference }}</span> can be looked up.</p>
          <form method="POST" action="{{ route('admin.orders.reconcile', $order) }}" data-adm-once data-confirm="Look up eSIM Go order {{ $po->provider_reference }}? This is read-only and never buys.">
            @csrf
            <div class="adm-field">
              <label for="reconcile-reason">Reason (optional)</label>
              <input type="text" id="reconcile-reason" name="reason" maxlength="500" value="{{ old('reason') }}">
            </div>
            <button class="adm-btn adm-btn-light" type="submit" data-busy-label="Looking up…">Reconcile now</button>
          </form>
        @endif
      </div>

      <div class="adm-order-action">
        <h3>Retry failed purchase</h3>
        <p class="adm-hint">Only for a purchase eSIM Go clearly refused (nothing bought). All payment, safety and feature-switch checks run again, the bundle and cost are re-validated with eSIM Go, then the normal fulfilment job is queued.</p>
        @if ($retryBlocked)
          <p class="adm-action-why"><span class="adm-badge">Unavailable</span> {{ $retryBlocked }}</p>
        @else
          <p class="adm-action-why"><span class="adm-badge adm-badge-warn">Available</span> The previous attempt failed and nothing was bought. A retry buys real eSIMs.</p>
          <form method="POST" action="{{ route('admin.orders.retry', $order) }}" data-adm-once data-confirm="Retry the eSIM Go purchase for {{ $order->order_number }}? This buys real eSIMs.">
            @csrf
            <div class="adm-field">
              <label for="retry-reason">Reason (optional)</label>
              <input type="text" id="retry-reason" name="reason" maxlength="500" value="{{ old('reason') }}" placeholder="e.g. eSIM Go balance topped up">
            </div>
            <label class="adm-check">
              <input type="checkbox" name="confirm" value="1" required>
              <span>I checked the cause of the failure and want to buy the eSIMs for this order again.</span>
            </label>
            <button class="adm-btn adm-btn-danger" type="submit" data-busy-label="Queuing…">Retry purchase</button>
          </form>
        @endif
      </div>

      {{-- Phase 9E: "eSIM ready" email. Only the email is affected — never fulfilment or a purchase. --}}
      @php
        $emailCanQueue = $emailTracking && $emailBlocked === null
            && ($email === null || in_array($email->status, [\App\Models\OrderNotification::STATUS_FAILED, \App\Models\OrderNotification::STATUS_SENDING], true));
      @endphp
      <div class="adm-order-action" data-email-panel data-email-status="{{ $email?->status ?? 'none' }}">
        <h3>Customer “eSIM ready” email</h3>
        <p class="adm-hint">Sent once per order after fulfilment, when every eSIM has activation details and a QR code. It contains links only — no activation data. Retrying never contacts eSIM Go and never buys anything.</p>
        @if (! $emailTracking)
          <p class="adm-action-why"><span class="adm-badge">Unavailable</span> Email tracking is not set up yet: the Phase 9E migration (order_notifications) has not been run on this database.</p>
        @else
          <p class="adm-action-why">
            <span class="adm-badge adm-badge-{{ ['sent' => 'ok', 'failed' => 'error', 'sending' => 'warn', 'pending' => 'info'][$email?->status] ?? '' }}">{{ $email ? \App\Models\OrderNotification::LABELS[$email->status] ?? $email->status : 'Not queued' }}</span>
            @if ($email)
              Attempts: {{ $email->attempts }}@if ($email->sent_at) · sent {{ $fmt($email->sent_at) }}@elseif ($email->last_attempt_at) · last attempt {{ $fmt($email->last_attempt_at) }}@endif
              @if ($email->last_error)<small class="adm-cell-sub">{{ $email->last_error }}</small>@endif
            @endif
          </p>
          @if ($emailCanQueue)
            <form method="POST" action="{{ route('admin.orders.notifications.esim-ready', $order) }}" data-adm-once
                  data-confirm="{{ $email?->status === 'sending' ? 'The previous email may already have been delivered (result not confirmed). Queue it again? The customer could receive it twice.' : 'Queue the “eSIM ready” email for '.$order->order_number.'? Only an email is sent.' }}">
              @csrf
              <div class="adm-field">
                <label for="email-reason">Reason (optional)</label>
                <input type="text" id="email-reason" name="reason" maxlength="500" placeholder="e.g. mail server was down">
              </div>
              <button class="adm-btn adm-btn-light" type="submit" data-busy-label="Queuing…">{{ $email ? 'Retry email' : 'Send email' }}</button>
            </form>
          @elseif ($email?->status === 'sent')
            <p class="adm-action-why"><span class="adm-badge">Unavailable</span> Already sent — it is not sent twice.</p>
          @elseif ($email?->status === 'pending')
            <p class="adm-action-why"><span class="adm-badge">Unavailable</span> Already queued (waiting for the queue worker).</p>
          @else
            <p class="adm-action-why"><span class="adm-badge">Unavailable</span> {{ $emailBlocked }}</p>
          @endif
        @endif
      </div>
    </div>

    <h3 class="adm-subsection-title">Action history</h3>
    @if ($actions->isEmpty())
      <p class="adm-hint">No administrative actions on this order yet.</p>
    @else
      <div class="adm-table-wrap">
        <table class="adm-table">
          <thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>State</th><th>Reason / result</th></tr></thead>
          <tbody>
            @foreach ($actions as $entry)
              @php
                $prev = $entry->response_body['previous_state'] ?? null;
                $new = $entry->response_body['new_state'] ?? null;
                $adminId = $entry->request_body['admin_user_id'] ?? null;
              @endphp
              <tr>
                <td class="adm-nowrap">{{ $fmt($entry->created_at) }}</td>
                <td data-label="Admin">{{ $admins[$adminId] ?? ('#'.$adminId) }}</td>
                <td data-label="Action"><span class="adm-badge adm-badge-{{ $entry->success ? 'ok' : (in_array($entry->operation, ['retry_rejected', 'action_blocked', 'reconciliation_incomplete', 'qr_regeneration_failed', 'email_retry_rejected'], true) ? 'warn' : '') }}">{{ \App\Support\AdminAudit::label($entry->operation) }}</span></td>
                <td data-label="State">
                  @if ($prev && $new && $prev !== $new)
                    <small class="adm-cell-sub">{{ $prev['fulfillment_status'] }} / {{ $prev['provider_order_status'] ?? '—' }} → {{ $new['fulfillment_status'] }} / {{ $new['provider_order_status'] ?? '—' }}</small>
                  @elseif ($prev)
                    <small class="adm-cell-sub">{{ $prev['fulfillment_status'] }} / {{ $prev['provider_order_status'] ?? '—' }}</small>
                  @endif
                </td>
                <td data-label="Reason / result">
                  @if ($entry->request_body['reason'] ?? null)<strong>{{ $entry->request_body['reason'] }}</strong>@endif
                  @if ($entry->response_body['note'] ?? null)<small class="adm-cell-sub">{{ $entry->response_body['note'] }}</small>@endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>

  <div class="adm-grid adm-grid-2">
    <div class="adm-card">
      <h2>Order</h2>
      <dl class="adm-dl">
        <dt>Order date</dt><dd>{{ $fmt($order->created_at) }}</dd>
        <dt>Subtotal</dt><dd>{{ number_format((float) $order->subtotal, 2) }} {{ $order->currency }}</dd>
        @if ((float) $order->discount_total > 0)<dt>Discount</dt><dd>−{{ number_format((float) $order->discount_total, 2) }} {{ $order->currency }}@if ($order->promo_code) ({{ $order->promo_code }})@endif</dd>@endif
        @if ((float) $order->tax_total > 0)<dt>Tax</dt><dd>{{ number_format((float) $order->tax_total, 2) }} {{ $order->currency }}</dd>@endif
        <dt>Total</dt><dd><strong>{{ number_format((float) $order->total, 2) }} {{ $order->currency }}</strong></dd>
        <dt>Paid at</dt><dd>{{ $fmt($order->paid_at) ?? '—' }}</dd>
        <dt>Fulfilled at</dt><dd>{{ $fmt($order->fulfilled_at) ?? '—' }}</dd>
        @if ($order->cancelled_at)<dt>Cancelled at</dt><dd>{{ $fmt($order->cancelled_at) }}</dd>@endif
      </dl>
    </div>

    <div class="adm-card">
      <h2>Customer</h2>
      <dl class="adm-dl">
        <dt>Billing name</dt><dd>{{ $order->billing_name }}</dd>
        <dt>Billing email</dt><dd>{{ $order->billing_email }}</dd>
        @if ($order->billing_country)<dt>Country</dt><dd>{{ $order->billing_country }}</dd>@endif
        @if ($order->user)
          <dt>Account</dt><dd>{{ $order->user->name }} <small class="adm-cell-sub">{{ $order->user->email }}</small></dd>
        @endif
      </dl>
    </div>
  </div>

  <div class="adm-card adm-table-wrap">
    <h2>Items</h2>
    <table class="adm-table">
      <thead><tr><th>Plan</th><th>Bundle code</th><th>Qty</th><th>Unit price</th><th>Line total</th><th>Provider cost (snapshot)</th></tr></thead>
      <tbody>
        @foreach ($order->items as $item)
          <tr>
            <td>{{ $item->plan_name }}@if ($item->plan) <small class="adm-cell-sub"><a href="{{ route('admin.plans.show', $item->plan) }}">Plan #{{ $item->plan->id }}</a></small>@endif</td>
            <td data-label="Bundle code"><span class="adm-code">{{ $item->bundle_code }}</span></td>
            <td data-label="Qty">{{ $item->quantity }}</td>
            <td class="adm-nowrap" data-label="Unit price">{{ number_format((float) $item->unit_price, 2) }} {{ $order->currency }}</td>
            <td class="adm-nowrap" data-label="Line total">{{ number_format((float) $item->line_total, 2) }} {{ $order->currency }}</td>
            <td class="adm-nowrap" data-label="Provider cost">{{ $item->unit_provider_cost !== null ? number_format((float) $item->unit_provider_cost, 2).' '.$item->provider_currency : '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="adm-card adm-table-wrap">
    <h2>Payments</h2>
    @if ($order->payments->isEmpty())
      <p class="adm-hint">No payment attempt yet.</p>
    @else
      <table class="adm-table">
        <thead><tr><th>Started</th><th>Gateway</th><th>Gateway order</th><th>Gateway payment</th><th>Amount</th><th>Status</th><th>Verified</th></tr></thead>
        <tbody>
          @foreach ($order->payments as $payment)
            <tr>
              <td class="adm-nowrap">{{ $fmt($payment->created_at) }}</td>
              <td data-label="Gateway">{{ ucfirst($payment->gateway) }}@if ($payment->method) <small class="adm-cell-sub">{{ $payment->method }}</small>@endif</td>
              <td data-label="Gateway order"><span class="adm-code">{{ $payment->gateway_order_id ?? '—' }}</span></td>
              <td data-label="Gateway payment"><span class="adm-code">{{ $payment->gateway_payment_id ?? '—' }}</span></td>
              <td class="adm-nowrap" data-label="Amount">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</td>
              <td data-label="Status">
                <span class="adm-badge adm-badge-{{ OrderLifecycle::paymentTone($payment->status) }}">{{ OrderLifecycle::paymentLabel($payment->status) }}</span>
                @if ($payment->failure_reason)<small class="adm-cell-sub">{{ $payment->failure_reason }}</small>@endif
              </td>
              <td class="adm-nowrap" data-label="Verified">{{ $fmt($payment->verified_at) ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="adm-grid adm-grid-2">
    <div class="adm-card">
      <h2>eSIM Go order</h2>
      @if ($po)
        <dl class="adm-dl">
          <dt>Status</dt><dd><span class="adm-code">{{ $po->status }}</span></dd>
          <dt>Our reference</dt><dd><span class="adm-code">{{ $po->our_reference }}</span></dd>
          <dt>eSIM Go reference</dt><dd><span class="adm-code">{{ $po->provider_reference ?? '—' }}</span></dd>
          <dt>Attempts sent</dt><dd>{{ $po->attempts }}</dd>
          <dt>Provider cost</dt><dd>{{ $po->total_provider_cost !== null ? number_format((float) $po->total_provider_cost, 2).' '.$po->provider_currency : '—' }}</dd>
          <dt>Sent at</dt><dd>{{ $fmt($po->submitted_at) ?? 'Not sent' }}</dd>
          <dt>Completed at</dt><dd>{{ $fmt($po->completed_at) ?? '—' }}</dd>
          @if ($po->last_error)<dt>Last error</dt><dd class="adm-text-error">{{ $po->last_error }}</dd>@endif
        </dl>
      @else
        <p class="adm-hint">No eSIM Go order yet. It is created by the fulfillment job after the payment is verified.</p>
      @endif
    </div>

    <div class="adm-card adm-table-wrap">
      <h2>eSIMs</h2>
      @if ($order->esims->isEmpty())
        <p class="adm-hint">No eSIMs stored yet.</p>
      @else
        <table class="adm-table">
          <thead><tr><th>ICCID</th><th>Bundle</th><th>Status</th><th>QR code</th><th>Stored</th></tr></thead>
          <tbody>
            @foreach ($order->esims as $esim)
              @php $qrState = $esimQr[$esim->id]['status']['state']; @endphp
              <tr>
                <td><a href="#esim-{{ $esim->id }}"><span class="adm-code">{{ $esim->iccid }}</span></a></td>
                <td data-label="Bundle"><span class="adm-code">{{ $esim->bundle_code }}</span></td>
                <td data-label="Status">{{ $esim->status }}</td>
                <td data-label="QR code"><span class="adm-badge adm-badge-{{ ['generated' => 'ok', 'failed' => 'error', 'unavailable' => 'warn'][$qrState] ?? '' }}">{{ ['generated' => 'Generated', 'failed' => 'Failed', 'unavailable' => 'Unavailable', 'not_generated' => 'Not generated'][$qrState] }}</span></td>
                <td class="adm-nowrap" data-label="Stored">{{ $fmt($esim->created_at) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <p class="adm-hint" style="margin:10px 0 0">Activation details are stored encrypted. QR codes and manual installation details are in “eSIM activation &amp; QR codes” below.</p>
      @endif
    </div>
  </div>

  {{-- Phase 9D: QR codes and manual installation details. Administrators only; page sent with Cache-Control: no-store. --}}
  @if ($order->esims->isNotEmpty())
    <section class="adm-card" aria-labelledby="esim-activation-title" data-section="esim-activation">
      <div class="adm-card-head">
        <h2 id="esim-activation-title">eSIM activation &amp; QR codes</h2>
        <span class="adm-hint">Made only from the activation details stored for each eSIM. Regenerating never contacts eSIM Go and never buys an eSIM.</span>
      </div>

      @foreach ($order->esims as $esim)
        @php
          $qrStatus = $esimQr[$esim->id]['status'];
          $payload = $esimQr[$esim->id]['payload'];
          $item = $order->items->firstWhere('id', $esim->order_item_id);
          $tone = ['generated' => 'ok', 'failed' => 'error', 'unavailable' => 'warn'][$qrStatus['state']] ?? '';
          $qrLabel = ['generated' => 'QR code generated', 'failed' => 'QR generation failed', 'unavailable' => 'Activation information unavailable', 'not_generated' => 'QR code not generated'][$qrStatus['state']];
        @endphp
        <article class="adm-esim" id="esim-{{ $esim->id }}" data-esim="{{ $esim->id }}" data-qr-state="{{ $qrStatus['state'] }}">
          <div class="adm-esim-qr">
            @if ($qrStatus['state'] === 'generated')
              <img src="{{ route('admin.orders.esims.qr', [$order, $esim]) }}" width="240" height="240" alt="QR code for eSIM {{ $esim->iccid }}">
              <a class="adm-btn adm-btn-light adm-btn-sm" href="{{ route('admin.orders.esims.qr', [$order, $esim]) }}?download=1">Download PNG</a>
            @else
              <div class="adm-esim-qr-empty" role="status">
                <strong>{{ $qrLabel }}</strong>
                <span>{{ $qrStatus['message'] }}</span>
              </div>
            @endif
          </div>

          <div class="adm-esim-body">
            <dl class="adm-dl">
              <dt>ICCID</dt><dd><span class="adm-code">{{ $esim->iccid }}</span></dd>
              <dt>Bundle</dt><dd>{{ $item?->plan_name ?? '—' }} <small class="adm-cell-sub"><span class="adm-code">{{ $esim->bundle_code }}</span></small></dd>
              <dt>Activation status</dt>
              <dd>
                <span class="adm-badge">{{ ucfirst($esim->status) }}</span>
                <small class="adm-cell-sub">{{ $esim->activated_at ? 'Activated '.$fmt($esim->activated_at) : 'Installation is not confirmed. A QR code being available does not mean the eSIM is installed.' }}</small>
              </dd>
              <dt>QR code</dt>
              <dd><span class="adm-badge adm-badge-{{ $tone }}">{{ $qrLabel }}</span> <small class="adm-cell-sub">{{ $qrStatus['message'] }}</small></dd>
            </dl>

            @if ($payload)
              <details class="adm-esim-manual">
                <summary>Show manual installation details</summary>
                <p class="adm-hint">For phones that cannot scan the QR code. Share only with the customer who owns this eSIM.</p>
                @foreach ([
                  'smdp' => ['SM-DP+ address', $payload->smdpHost],
                  'code' => ['Activation code', $payload->matchingId],
                  'lpa' => ['Full activation code (some Android phones)', $payload->toString()],
                ] as $key => [$label, $value])
                  <div class="adm-copy-field">
                    <label for="esim-{{ $esim->id }}-{{ $key }}">{{ $label }}</label>
                    <div class="adm-copy-row">
                      <input type="text" id="esim-{{ $esim->id }}-{{ $key }}" value="{{ $value }}" readonly spellcheck="false" autocomplete="off">
                      <button type="button" class="adm-btn adm-btn-light adm-btn-sm" data-copy="esim-{{ $esim->id }}-{{ $key }}">Copy</button>
                    </div>
                  </div>
                @endforeach
              </details>

              <form class="adm-esim-regen" method="POST" action="{{ route('admin.orders.esims.qr.regenerate', [$order, $esim]) }}" data-adm-once
                    data-confirm="Regenerate the QR code for eSIM {{ $esim->iccid }} from its stored activation details? eSIM Go is not contacted and nothing is bought.">
                @csrf
                <div class="adm-field">
                  <label for="esim-{{ $esim->id }}-reason">Reason (optional)</label>
                  <input type="text" id="esim-{{ $esim->id }}-reason" name="reason" maxlength="500" placeholder="e.g. QR image missing">
                </div>
                <button class="adm-btn adm-btn-light" type="submit" data-busy-label="Generating…">{{ $qrStatus['state'] === 'generated' ? 'Regenerate QR code' : 'Generate QR code' }}</button>
              </form>
            @else
              <p class="adm-action-why"><span class="adm-badge">Unavailable</span> No QR code or manual installation details can be shown.</p>
            @endif
          </div>
        </article>
      @endforeach

      <div class="adm-esim-help">
        <h3 class="adm-subsection-title">Installation instructions (for the customer)</h3>
        <ol>
          <li>Open the phone’s <strong>Settings</strong>.</li>
          <li>Find <strong>Mobile Network</strong> or <strong>Cellular</strong> settings.</li>
          <li>Select <strong>Add eSIM</strong>.</li>
          <li>Scan the displayed QR code.</li>
          <li>Follow the phone’s instructions to complete installation.</li>
        </ol>
        <p class="adm-hint"><strong>If scanning is not possible:</strong> choose “Enter details manually” (wording varies by phone) and enter the SM-DP+ address and the activation code above. Some Android phones ask for the full activation code (starting with <span class="adm-code">LPA:1$</span>) instead.
          An eSIM can normally be installed only once, and the phone needs an internet connection. Generating or showing a QR code does not install or activate the eSIM.</p>
      </div>
    </section>
  @endif

  <div class="adm-card adm-table-wrap">
    <h2>Integration log</h2>
    @if ($order->integrationLogs->isEmpty())
      <p class="adm-hint">No log entries for this order.</p>
    @else
      <table class="adm-table">
        <thead><tr><th>Time</th><th>Service</th><th>Step</th><th>Result</th><th>Message</th></tr></thead>
        <tbody>
          @foreach ($order->integrationLogs as $log)
            <tr>
              <td class="adm-nowrap">{{ $fmt($log->created_at) }}</td>
              <td data-label="Service">{{ $log->service }}</td>
              <td data-label="Step"><span class="adm-code">{{ $log->operation }}</span></td>
              <td data-label="Result">
                <span class="adm-badge adm-badge-{{ $log->success ? 'ok' : 'error' }}">{{ $log->success ? 'OK' : 'Failed' }}</span>
                @if ($log->status_code)<small class="adm-cell-sub">HTTP {{ $log->status_code }}</small>@endif
              </td>
              <td data-label="Message">{{ \Illuminate\Support\Str::limit($log->error_message ?: (is_array($log->request_body) && is_string($log->request_body['note'] ?? null) ? $log->request_body['note'] : ''), 300) ?: '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <p class="adm-hint" style="margin:10px 0 0">Most recent 50 entries. Request and response bodies are not shown.</p>
    @endif
  </div>

  @include('admin.partials.confirm-script')
  <script>
    // Prevent double submission: after the confirmation, disable the button and show progress.
    document.querySelectorAll('form[data-adm-once]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (event.defaultPrevented) return;
        if (form.dataset.sent) { event.preventDefault(); return; }
        form.dataset.sent = '1';
        var button = form.querySelector('button[type=submit]');
        if (button) { button.disabled = true; button.textContent = button.getAttribute('data-busy-label') || 'Working…'; }
      });
    });

    // Phase 9D: copy manual installation fields.
    document.querySelectorAll('button[data-copy]').forEach(function (button) {
      button.addEventListener('click', function () {
        var input = document.getElementById(button.getAttribute('data-copy'));
        if (!input) return;
        var finished = false;
        var done = function (ok) {
          if (finished) return;
          finished = true;
          if (!ok) { input.focus(); input.select(); }
          button.textContent = ok ? 'Copied' : 'Press Ctrl+C';
          setTimeout(function () { button.textContent = 'Copy'; }, 2000);
        };
        var legacy = function () {
          input.select();
          var ok = false;
          try { ok = document.execCommand('copy'); } catch (e) {}
          done(ok);
        };
        if (navigator.clipboard && window.isSecureContext) {
          // Some browsers never settle the promise without clipboard permission: fall back after 1.5 s.
          setTimeout(function () { done(false); }, 1500);
          navigator.clipboard.writeText(input.value).then(function () { done(true); }, legacy);
        } else {
          legacy();
        }
      });
    });
  </script>
@endsection
