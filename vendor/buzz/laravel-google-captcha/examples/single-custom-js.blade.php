<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>
</head>
<body>
<form>
    {!! app('captcha')->display(['add-js' => false]) !!}
</form>
{!! app('captcha')->displayJs() !!}
</body>
</html>
