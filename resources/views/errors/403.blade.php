@extends('errors::minimal')

@section('title', __('Forbidden'))
@section('code', '403')
@section('message')
    <div>{{ __($exception->getMessage() ?: 'Forbidden') }}</div>
    <a href="{{ route('today') }}" style="display:inline-block;margin-top:20px;padding:11px 18px;border-radius:999px;background:#009de6;color:#fff;text-decoration:none;font-family:ui-sans-serif,system-ui,sans-serif;font-size:14px;font-weight:700;">Back to Today</a>
@endsection
