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


        var confirm_subscription_coupon = function(msg, title, tmp) {
            swal({
                title : title,
                text : msg,
                showCancelButton : true,
                confirmButtonClass : "btn-danger",
                confirmButtonText : "확인",
                cancelButtonText : "취소",
                closeOnConfirm : false,
                closeOnCancel : true
            }, function(isConfirm) {
                if (isConfirm) {
                    //console.log('Y'+tmp);
                    subscription_coupon(tmp);
                }else{
                    console.log('N'+tmp);
                }

            });
        }
        var confirm_subscription_move = function(msg, title) {
            swal({
                title : title,
                text : msg,
                showCancelButton : true,
                confirmButtonClass : "btn-danger",
                confirmButtonText : "구독 혜택 확인하기",
                cancelButtonText : "닫기",
                closeOnConfirm : false,
                closeOnCancel : true
            }, function(isConfirm) {
                if (isConfirm) {
                    window.ReactNativeWebView?.postMessage(
                        JSON.stringify({
                            stack: "마이페이지",
                            page: "MySub",
                        }),
                    );
                }else{
                    //console.log('N'+tmp);
                }

            });
        }



        function subscription_coupon(tmp){


            $('#no_subscription_product').val(tmp);


            $.ajax({
                type: "post",
                url: "/v1/event/get_subscription_member_coupon",
                dataType: "json",
                async: false,
                cache: false,
                data: $('#frm').serialize(),
                success: function(res, status, xhr) {
                    if(res.result){
                        confirm_subscription_move("선택하신 오윈 구독 \n1개월 무료 이용이 적용되었습니다!","");
                    }else{
                        alert(res.message);
                    }
                },
                error: function(xhr, status, err) {
                    var result = JSON.parse(xhr.responseText)
                    alert(result.message);
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
            <input type="hidden" name="no_subscription_product" id="no_subscription_product" value="">
            <input type="hidden" name="affiliate_code" id="affiliate_code" value="OWIN">
            <input type="hidden" name="no_subscription_affiliate" id="no_subscription_affiliate" value="2">
            <input type="hidden" name="no_limit" id="no_limit" value="100">
            <input type="hidden" name="bizKind" id="bizKind" value="FNB">
            <input type="hidden" name="use_coupon_yn" id="use_coupon_yn" value="N">


            <div class="main_contents">
                <img src="https://images.owinpay.com/data2/owin/editor/202306/20230616_1_1.jpg" id="main_img" >
                <a href="https://forms.gle/F526yCXKqaC5xynd7"><img src="https://images.owinpay.com/data2/owin/editor/202306/20230616_1_2.jpg" id="main_img" ></a>
                <img src="https://images.owinpay.com/data2/owin/editor/202306/20230616_1_3.jpg" id="main_img" >
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

        $('#subscription_1').off('click').on("click",function(event)
        {
            var no_user = $("#no_user").val();
            if(no_user.length != 16){
                confirm_login('구독신청 하시기 위해서는 로그인을 하셔야 합니다.',"");
                return;
            }
            var tmp = $("#subscription_1").attr("value");
            confirm_subscription_coupon("선택하신 타입으로 \n오윈 구독하시겠어요?","",tmp);
        });

        $('#subscription_2').off('click').on("click",function(event)
        {
            var no_user = $("#no_user").val();
            if(no_user.length != 16){
                confirm_login('구독신청 하시기 위해서는 로그인을 하셔야 합니다.',"");
                return;
            }
            var tmp = $("#subscription_2").attr("value");
            confirm_subscription_coupon("선택하신 타입으로 \n오윈 구독하시겠어요?","",tmp);
        });

        $('#subscription_3').off('click').on("click",function(event)
        {
            var no_user = $("#no_user").val();
            if(no_user.length != 16){
                confirm_login('구독신청 하시기 위해서는 로그인을 하셔야 합니다.',"");
                return;
            }
            var tmp = $("#subscription_3").attr("value");
            confirm_subscription_coupon("선택하신 타입으로 \n오윈 구독하시겠어요?","",tmp);
        });
        $('#subscription_33').off('click').on("click",function(event)
        {

            window.ReactNativeWebView?.postMessage(
                JSON.stringify({
                    stack: "마이페이지",
                    page: "EventPage",
                    data: {
                        no: 105,
                        ds_thumb: "/data2/bbs_event/202304/ds_thumb1682670933.png",
                        ds_popup_thumb: "/data2/bbs_event/202304/ds_popup_thumb1682670933.png",
                        ds_detail_url: null,
                        ds_title: "[이벤트] 하나카드 2차 프로모션",
                        url: '<div><div><div><div><div><div><img src="https://images.owinpay.com/data2/owin/editor/202305/9a691cffb110c3b3376bb83a61ee6a42.png" border="0"></div><br></div><br></div><div><img src="http://app.owin.kr/data2/owin/editor/202305/bf67310554ac440e973bd766ff94f790.png" border="0"></div><br></div><br></div><br></div><div><a href="https://m.hanacard.co.kr/MKCDCM1010M.web?CD_PD_SEQ=15618&amp;utm_source=owin&amp;utm_medium=affiliates&amp;utm_campaign=202212_owin" target="_blank"><img src="http://app.owin.kr/data2/owin/editor/202301/33c8356364cedd1e4c63e26a3dc3f6a3.jpg" border="0"></a></div>\r\n<div>\r\n<img src="http://app.owin.kr/data2/owin/editor/202301/db525a640f66882dee611a4fe9ddb49c.jpg" border="0">\r\n</div>\r\n<div>\r\n<a href="https://owin.page.link/VRTeLnkEfWHr3scu8" target="_blank">\r\n<img src="http://app.owin.kr/data2/owin/editor/202301/ced60bcb2f6f3755c86d5f92a874008a.jpg" border="0">\r\n</a>\r\n</div>\r\n<div><br></div>\r\n\r\n\r\n\r\n\r\n',
                        dt_reg: "2022-12-29T09:32:54.000000Z",
                    },
                }),
            );
        });


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
