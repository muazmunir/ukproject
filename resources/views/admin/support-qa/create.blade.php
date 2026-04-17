@extends('layouts.admin')
@section('title','New Question')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-support-qa.css') }}">
@endpush

@section('content')
<div class="qa-wrap">
  <div class="qa-card">
    <div class="qa-head">
      <div>
        <h1 class="qa-title">Ask a Question</h1>
        <div class="qa-sub text-capitalize">
          This question will be visible to all admins and managers.
          A manager will take and answer it.
        </div>
      </div>
      <a class="qa-btn ghost" href="{{ route('admin.support.questions.index') }}">Back</a>
    </div>

    <div style="padding:18px;">
      <form method="post" action="{{ route('admin.support.questions.store') }}">
        @csrf

        <div class="mb-3">
          <label class="form-label">Question</label>
          <textarea class="form-control"
                    name="question"
                    rows="6"
                    required
                    placeholder="Describe The Issue Or Question In Detail.">{{ old('question') }}</textarea>
        </div>

        <button class="qa-btn" type="submit">
          Post Question
        </button>
      </form>
    </div>
  </div>
</div>
@endsection
