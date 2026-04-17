@extends('layouts.app')

@section('content')
<style>
  :root{
    --za-bg: #f7f8fb;
    --za-card: #ffffff;
    --za-text: #0f172a;
    --za-muted: #64748b;
    --za-border: rgba(15, 23, 42, .10);
    --za-shadow: 0 18px 45px rgba(15, 23, 42, .10);
    --za-radius: 18px;

    --za-primary: #000000ff; /* button */
    --za-primary-hover: #1d4ed8;

    --za-soft: rgba(37, 99, 235, .08); /* info badge bg */
    --za-soft-border: rgba(37, 99, 235, .18);

    --za-danger: #ef4444;
    --za-success: #16a34a;
  }

  .kyc-wrap{
    min-height: 72vh;
    display: grid;
    place-items: center;
    padding: 24px 12px;
    background: radial-gradient(1100px 420px at 50% 0%, rgba(37,99,235,.08), transparent 60%),
                linear-gradient(180deg, var(--za-bg), #fff);
  }

  .kyc-card{
    width: 100%;
    max-width: 520px;
    background: var(--za-card);
    border: 1px solid var(--za-border);
    border-radius: var(--za-radius);
    box-shadow: var(--za-shadow);
    overflow: hidden;
  }

  .kyc-head{
    padding: 22px 22px 14px;
    text-align: center;
    border-bottom: 1px solid rgba(15, 23, 42, .06);
    background: linear-gradient(180deg, rgba(37,99,235,.06), transparent);
  }

  .kyc-logo{
    width: 78px;
    height: 78px;
    margin: 0 auto 10px;
    border-radius: 16px;
    background: #fff;
    border: 1px solid rgba(15,23,42,.08);
    box-shadow: 0 10px 24px rgba(15,23,42,.10);
    display: grid;
    place-items: center;
    overflow: hidden;
  }
  .kyc-logo img{
    width: 70%;
    height: 70%;
    object-fit: contain;
    display: block;
  }

  .kyc-title{
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: black;
    letter-spacing: .2px;
  }

  .kyc-sub{
    margin: 10px 0 0;
    color: black;
    font-size: 14px;
    line-height: 1.55;
  }

  .kyc-body{
    padding: 18px 22px 22px;
  }

  .kyc-badge{
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 14px;
    background: var(--za-soft);
    border: 1px solid var(--za-soft-border);
    color: var(--za-text);
    margin: 2px 0 14px;
    width: 100%;
  }
  .kyc-dot{
    width: 10px; height: 10px;
    border-radius: 50%;
    background: var(--za-primary);
    box-shadow: 0 0 0 4px rgba(37,99,235,.14);
    flex: 0 0 auto;
  }
  .kyc-badge strong{ font-weight: 600; }
  .kyc-badge span{ color: black; font-size: 13px; }

  .kyc-actions{
    display: grid;
    gap: 10px;
    margin-top: 10px;
  }

  .btn-za{
    width: 100%;
    border: 0;
    border-radius: 14px;
    padding: 12px 14px;
    font-weight: 600;
    letter-spacing: .2px;
    cursor: pointer;
    transition: transform .12s ease, box-shadow .15s ease, background-color .15s ease, opacity .15s ease;
  }
  .btn-za:active{ transform: translateY(1px); }

  .btn-za-primary{
    background: var(--za-primary);
    color: #fff;
    box-shadow: 0 10px 20px rgba(37,99,235,.25);
  }
  .btn-za-primary:hover{ background: black; }

  .btn-za-ghost{
    background: #f1f5f9;
    color: var(--za-text);
    border: 1px solid rgba(15,23,42,.10);
  }
  .btn-za-ghost:hover{ background: #e8eef6; }

  .kyc-msg{
    margin-top: 14px;
    padding: 10px 12px;
    border-radius: 14px;
    font-weight: 600;
    font-size: 13px;
  }
  .kyc-msg.success{
    color: var(--za-success);
    background: rgba(22,163,74,.08);
    border: 1px solid rgba(22,163,74,.18);
  }
  .kyc-msg.error{
    color: var(--za-danger);
    background: rgba(239,68,68,.08);
    border: 1px solid rgba(239,68,68,.18);
  }

  .kyc-foot{
    text-align: center;
    color: var(--za-muted);
    font-size: 12px;
    padding-top: 10px;
  }

  @media (max-width: 420px){
    .kyc-head{ padding: 18px 16px 12px; }
    .kyc-body{ padding: 14px 16px 18px; }
    .kyc-title{ font-size: 18px; }
  }
</style>

<div class="kyc-wrap">
  <div class="kyc-card">

    <div class="kyc-head">
      {{-- Logo (adjust path) --}}
      <div class="kyc-logo">
        <img src="/assets/shield_secure.png" alt="ZAIVIAS">
      </div>

      <h1 class="kyc-title">Coach Verification Under Review</h1>

      <span class="kyc-sub text-capitalize">
        Your KYC documents have been submitted and are currently <strong>pending approval</strong>.
        You cannot access the coach dashboard until an admin approves your profile.
      </span>
    </div>

    <div class="kyc-body">
      <div class="kyc-badge">
        <span class="kyc-dot"></span>
        <div>
          <strong>Status: Under Review</strong><br>
          <span class="kyc-sub text-capitalize">We’ll notify you as soon as it’s approved.</span>
        </div>
      </div>

      <div class="kyc-actions">
        <form method="POST" action="{{ route('role.switch') }}">
          @csrf
          <input type="hidden" name="role" value="client">
          <button type="submit" class="btn-za btn-za-primary">
            Switch to Client Account
          </button>
        </form>

        {{-- <a href="{{ route('coach.apply') }}" class="btn-za btn-za-ghost" style="text-decoration:none; display:inline-block; text-align:center;">
          Go to Coach Apply Page
        </a> --}}
      </div>

      @if(session('success'))
        <div class="kyc-msg success">{{ session('success') }}</div>
      @endif

      @if(session('error'))
        <div class="kyc-msg error">{{ session('error') }}</div>
      @endif

      {{-- <div class="kyc-foot">
        Need help? Contact support from your account menu.
      </div> --}}
    </div>

  </div>
</div>
@endsection
