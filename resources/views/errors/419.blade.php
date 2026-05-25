@extends('errors.layout')

@section('title', '419 Сессия устарела')
@section('code', '419')
@section('status', 'Сессия устарела')
@section('message', 'Защитный токен формы больше не действителен. Обновите страницу, повторите действие или вернитесь назад и отправьте форму еще раз.')
@section('action_url', 'javascript:location.reload()')
@section('action_label', 'Обновить страницу')
