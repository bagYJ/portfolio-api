<html>
<head>
    <meta http-equiv=Content-Type content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: NanumGothic;
            word-wrap:break-word;
        }

        .title {
            width: 100%;
            margin: 0 0 26px;
            font-size: 14px;
            font-weight: bold;
            font-stretch: normal;
            font-style: normal;
            line-height: 1.57;
            letter-spacing: normal;
            text-align: center;
            color: #2e2e2e;
        }

        .sub-title {
            font-size: 13px;
            color: #707070;
        }

        .item {
            font-size: 10px;
            font-weight: bold;
            color: #707070;
            text-align: center;
        }
    </style>
</head>
<body>
<p class="title">
    오윈서비스 입점업체
</p>
<p class="title sub-title">
    {{ $bizKindName }} 입점업체
</p>
@foreach($shops as $shop)
    <p class="item">
        {{ $shop['nm_partner'] }} {{ $shop['nm_shop'] }}
    </p>
@endforeach
</body>
</html>
