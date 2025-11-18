@extends('layouts.app')

@section('title', 'Finish')

@section('content')
    <div
        class="w-full bg-zinc-800 border border-zinc-700 @if(!session('is_frame')) max-w-xl rounded-2xl @endif shadow-xl sm:p-8 p-4">
        <h2 class="text-2xl font-bold text-white mb-6 text-center">Мы успешно приняли ваш заказ</h2>
        <a href="{{session('is_frame') ? route('redeem.step1') . '?is_frame=1' : route('redeem.step1')}}">
            <button
                class="cursor-pointer w-full bg-blue-600 hover:bg-blue-700 transition-colors duration-200 text-white font-semibold py-2 px-4 rounded-xl shadow-md focus:ring-2 focus:ring-blue-600 focus:outline-none">
                Активировать новый код
            </button>
        </a>
    </div>
@endsection
