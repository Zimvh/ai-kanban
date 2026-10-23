@extends('layouts.app', ['title' => 'Register'])

@section('content')
<div class="max-w-sm mx-auto mt-12 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <h1 class="text-lg font-semibold mb-4">Create account</h1>
    <form method="POST" action="{{ route('register') }}" class="space-y-3">
        @csrf
        <input name="name" value="{{ old('name') }}" placeholder="Your name" required autofocus
               class="w-full rounded-md border border-slate-300 px-3 py-2">
        <input name="email" type="email" value="{{ old('email') }}" placeholder="Email" required
               class="w-full rounded-md border border-slate-300 px-3 py-2">
        <input name="password" type="password" placeholder="Password (min 8)" required
               class="w-full rounded-md border border-slate-300 px-3 py-2">
        <input name="password_confirmation" type="password" placeholder="Confirm password" required
               class="w-full rounded-md border border-slate-300 px-3 py-2">
        <button class="w-full rounded-md bg-indigo-600 text-white py-2 font-medium hover:bg-indigo-700">Register</button>
    </form>
    <p class="text-sm text-slate-500 mt-4">Have an account? <a href="{{ route('login') }}" class="text-indigo-600">Log in</a></p>
</div>
@endsection
