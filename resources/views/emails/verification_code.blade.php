<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title> Veda Billing : OTP Code </title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter&family=Roboto&display=swap');
    </style>
        <style>
        .im {
            color: #212121 !important;
        }
        .socials img {
            height: 22px !important;
            width: 22px !important;
        }
    </style>
</head>
<!-- min-height:100vh; -->
<body style="margin:0;padding:0;background:#eee!important;position:relative;font-size:100%;font-family:Roboto">
    <div style="width:100%;padding:20px 0;display:-webkit-box;display:-ms-flexbox;display:flex;-ms-flex-wrap:wrap;flex-wrap:wrap;-webkit-box-align:center;-ms-flex-align:center;align-items:center;">
        <div style="width:600px;height:800px;margin:0 auto;background:#FFFFFF;padding:35px 35px;">
            <div style="margin-bottom:32px;">
                <div style="max-width:370px;margin:0 auto;width:95%;">
                    <!-- <img src="https://veda-app.s3.ap-south-1.amazonaws.com/assets/2/about/2023-04-17/pjpXLl9Lek1EOY77-1681731117.png" alt="Veda" style="width:102px;margin:0 auto;display:block;margin-bottom:40px;"> -->
                    <img src="https://veda-app.com/logo.png" alt="Veda" style="width:102px;margin:0 auto;display:block;margin-bottom:40px;">
                </div>
                <div>
                    <p style="font-style:normal;font-weight:400;font-size:16px;line-height:24px;color:#212121;margin-bottom:32px;"><span style="color: #212121;">As a part of our security protocol, we have generated a <b>One-Time Password(OTP)</b> for your login.</span><p>

                        <p style="font-style:normal;font-weight:400;font-size:16px;line-height:24px;color:#212121;margin-bottom:32px;">Please use the following <i><b>OTP code</b></i> to authenticate and access the account. </p>

                    <div style="text-align:center;line-height:3.5;padding:16px;gap:10px;height:55px;background:#2D66F5;border-radius:2px;">
                        <p style="font-style:normal;font-weight:700;font-size:20px;line-height:23px;letter-spacing:0.64em;color:#FFFFFF;margin-top:15px">{{ $code }}</p>
                    </div>
                    <div style="display:flex;flex-direction:row;align-items:center;align-items:flex-start;margin-top:16px;">
                        <div style="margin-right:10px;">
                            <img src="https://img.icons8.com/?size=96&id=82742&format=png&color=737373" alt="info" style="width: 16px;">
                        </div>
                        <p style="font-style:normal;font-weight:400;font-size:14px;line-height:23px;color:#212121;margin:0;">Please note that this OTP is valid only for a limited time ({{ $verification_code_expiry_time }} minutes) and for a single login attempt. Do not share this OTP with anyone, 
                            including us. If you did not request an OTP or if you have any concerns about the security of your account, 
                            Please contact our customer support immediately.</p>
                    </div>
                    <div style="width:241px;height:19px;margin:0 auto;">
                        <p style="font-style:italic;font-weight:500;font-size:16px;line-height:19px;text-align:center;color:#212121;">Thank you for using our service.</p>
                    </div>
                </div>
            </div>
            <div style="border-top:1px solid #F1F1F1;">
                <p style="width:216px;height:17px;font-style:italic;font-weight:400;font-size:14px;line-height:17px;text-align:center;color:#ADADAD;margin-top:16px">Please do not reply to this email.</p>
            </div>
            <div>
                <div style="max-width:370px;margin:0 auto;width:95%;margin-bottom:30px;">
                    <img src="https://veda-app.com/logo.png" alt="Veda" style="width:53px;margin:0 auto;display:block;">
                </div>
                <div>
                    <p style="font-style:normal;font-weight:300;font-size:12px;line-height:15px;text-align:center;color:#212121;">Jawalakhel, Lalitpur</p>
                </div>
                <div style="margin-top:20px">
                    <p style="font-style:normal;font-weight:300;font-size:12px;line-height:15px;text-align:center;color:#212121;">+977-015971473</p>
                </div>
                <div style="width:85px;margin:0 auto;">
                    <a href="https://veda-app.com/" target="_blank" style="font-style:normal;font-weight:300;font-size:12px;line-height:15px;text-align:center;text-decoration-line:underline;color:#2D66F5;">veda-app.com</a>
                </div>
                <div style="text-align:center;margin-top:20px;" class="socials">
                    <a style="width:24px;height:19.95px;font-size:20px;text-decoration-line:none;" href="https://www.facebook.com/tryveda" title="facebook">
                        <img src="https://img.icons8.com/?size=96&id=8818&format=png&color=737373" alt="facebook" />
                    </a>
                    <a style="width:24px;height:19.95px;font-size:20px;text-decoration-line:none;margin:0 30px;" href="https://www.instagram.com/veda.app" target="_blank" title="Instagram">
                        <img src="https://img.icons8.com/?size=96&id=85154&format=png&color=737373" alt="instagram" />

                    </a>
                    <a style="width:24px;height:19.95px;font-size:20px;text-decoration-line:none;" href="https://www.linkedin.com/company/veda-app" target="_blank" title="linkedin">
                        <img src="https://img.icons8.com/?size=100&id=8808&format=png&color=737373" alt="instagram" />
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
