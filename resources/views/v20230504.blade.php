<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport"		content="height=device-height,width=device-width" />
    <meta property="og:image"	content="/resource/images/content/use/img_sec2_gas.jpg">
    <title>FNB 쿠폰 이벤트 </title>
    <link href="https://fonts.googleapis.com/css2?family=Nanum+Gothic:wght@700;800&display=swap" rel="stylesheet">
    <style>
        body,div,ul,li,dl,dt,dd,ol,p,h1,h2,h3,h4,h5,h6,h7,h8,h9,h10,form,td,th {margin:0;padding:0;}
        /*html,body {font-family:'NanumBarunGothicLight', sans-serif;color:#2a2a2a;height:100%;letter-spacing:-0.05em;min-width:320px;}*/
        a {color:#3d3d3d; text-decoration:none;}
        img {border:0;vertical-align:middle;}
        ul,li {list-style:none;}

        #container {}
        #container .main_contents{width:100%;}
        #container .main_contents img {width:100%;}
        #container .apply_btn {
            width:100%;
            position:fixed;
            left:0px;
            bottom:0px;
            background-color:#072f52;
            color: #ffffff;
            text-align:center;
            font-size: 18pt;
            font-family: Nanum Gothic, sans-serif;
        }

    </style>
</head>

<body>
<!-- wrap -->
<div id="wrap">
    <!-- containe r -->
    <div id="container">
        <form id="frm" name="frm" method="post">
            <input type="hidden" name="no_user" value="">
            <input type="hidden" name="event_version" value="">
            <div class="main_contents">
                <a href="#" id="apply_btn" ><img src="https://images.owinpay.com/data2/owin/editor/202304/v20230503.png" id="main_img" ></a>
                <div class="contents"></div>
            </div>
        </form>
    </div>
    <!--//container -->

</div>
<!-- //wrap -->


<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<script>

    $( document ).ready(function() {

        $('#apply_btn').off('click').on("click",function(event)
        {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            $.ajax({
                type: "post",
                url: "/event/get_fnb_member_coupon",
                dataType: "json",
                async: false,
                cache: false,
                data: $('#frm').serialize(),
                success: function(res, status, xhr) {
                    if(res.result_code == 1)
                    {
                        alert(res.sccess_msg);
                    }
                    else
                    {
                        alert(res.error_msg);
                    }
                },
                error: function(xhr, status, err) {
                    alert(err);
                }
            })

            setTimeout( function () {
                $("#apply_btn").attr('style', 'display:block')
            }, 80);

        })

    });

</script>

</body>
</html>
