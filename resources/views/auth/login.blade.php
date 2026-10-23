@extends('layouts.app', ['title' => 'Log in'])

@section('content')
<div class="max-w-sm mx-auto mt-12 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <h1 class="text-lg font-semibold mb-4">Log in</h1>
    <form method="POST" action="{{ route('login') }}" class="space-y-3">
        @csrf
        <input name="email" type="email" value="{{ old('email') }}" placeholder="Email" required autofocus
               class="w-full rounded-md border border-slate-300 px-3 py-2">
        <input name="password" type="password" placeholder="Password" required
               class="w-full rounded-md border border-slate-300 px-3 py-2">
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" checked> Remember me
        </label>
        <button class="w-full rounded-md bg-indigo-600 text-white py-2 font-medium hover:bg-indigo-700">Log in</button>
    </form>
    @if (config('app.allow_registration'))
        <p class="text-sm text-slate-500 mt-4">No account? <a href="{{ route('register') }}" class="text-indigo-600">Register</a></p>
    @endif
</div>
@endsection
