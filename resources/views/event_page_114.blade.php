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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.css" />
    <script type="text/javascript">
        var alert = function(msg, type) {
            swal({
                title : '',
                text : msg,
                type : type,
                customClass : 'sweet-size',
                showConfirmButton : true
            });
        }
        var confirm_login = function(msg, title) {
            swal({
                title : title,
                text : msg,
                showCancelButton : true,
                confirmButtonClass : "btn-danger",
                confirmButtonText : "로그인하기",
                cancelButtonText : "닫기",
                closeOnConfirm : false,
                closeOnCancel : true
            }, function(isConfirm) {
                if (isConfirm) {
                    //console.log('Y'+tmp);
                    window.ReactNativeWebView?.postMessage(
                        JSON.stringify({
                            stack: 'Others',
                            page: 'Login',
                        }),
                    );
                }else{
                }

            });
        }

    </script>
</head>
<body>
<!-- wrap -->
<div id="wrap">
    <!-- containe r -->
    <div id="container">
        <form id="frm" name="frm" method="post">
            <input type="hidden" name="no_user" id="no_user" value="<?php echo $no_user; ?>">
            <input type="hidden" name="event_version" id="event_version" value="v20230503">
            <div class="main_contents">
                <img src="https://images.owinpay.com/data2/owin/editor/202305/20230531_1.jpg" id="main_img" >
                <a id="apply_btn" ><img src="https://images.owinpay.com/data2/owin/editor/202305/20230531_2.jpg" id="main_img" ></a>
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
            var no_user = $("#no_user").val();
            if(no_user.length != 16){
                confirm_login('쿠폰을 받기 위해서는 로그인을 하셔야 합니다.',"");
                return;
            }
            $.ajax({
                type: "post",
                url: "/v1/event/get_fnb_member_coupon",
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
            });

            setTimeout( function () {
                $("#apply_btn").attr('style', 'display:block')
            }, 80);

        });

    });

</script>

</body>
</html>
