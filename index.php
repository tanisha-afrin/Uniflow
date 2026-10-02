<?php
require_once "config/auth.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>UniFlow — One Campus. One Smart Flow.</title>

    <meta
        name="description"
        content="UniFlow connects students, university services and the campus community in one smart platform."
    >

    <style>
        :root{
            --orange:#f97316;
            --orange-main:#ea580c;
            --orange-dark:#c2410c;
            --orange-deep:#9a3412;
            --orange-light:#fb923c;
            --orange-soft:#fed7aa;

            --cream:#fffaf3;
            --cream-2:#fff7ed;
            --white:#ffffff;

            --ink:#2b1710;
            --ink-soft:#5f4035;
            --muted:#8a6d60;

            --border:rgba(154,52,18,.12);
            --border-strong:rgba(154,52,18,.20);

            --shadow-sm:0 10px 25px rgba(120,53,15,.08);
            --shadow-md:0 18px 55px rgba(120,53,15,.12);
            --shadow-lg:0 35px 100px rgba(120,53,15,.18);

            --radius-xl:32px;
            --radius-lg:24px;
            --radius-md:18px;

            --container:1240px;
        }

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        html{
            scroll-behavior:smooth;
        }

        body{
            position:relative;
            min-height:100vh;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            color:var(--ink);

            background:
                radial-gradient(
                    circle at 10% 15%,
                    rgba(249,115,22,.18),
                    transparent 24%
                ),
                radial-gradient(
                    circle at 90% 18%,
                    rgba(234,88,12,.14),
                    transparent 24%
                ),
                linear-gradient(
                    135deg,
                    #fffefb 0%,
                    #fffaf3 32%,
                    #fff4e7 66%,
                    #ffe9d1 100%
                );

            overflow-x:hidden;
        }

        body::before{
            content:"";
            position:fixed;
            inset:-20%;
            z-index:-20;
            pointer-events:none;

            background:
                repeating-radial-gradient(
                    circle at 50% 50%,
                    rgba(234,88,12,.025) 0 2px,
                    transparent 2px 18px
                );

            opacity:.9;
        }

        body::after{
            content:"";
            position:fixed;
            inset:0;
            z-index:-19;
            pointer-events:none;

            background:
                linear-gradient(
                    90deg,
                    transparent 0%,
                    rgba(255,255,255,.35) 48%,
                    transparent 100%
                );

            mix-blend-mode:soft-light;
        }

        a{
            color:inherit;
            text-decoration:none;
        }

        button,
        a{
            -webkit-tap-highlight-color:transparent;
        }

        img{
            max-width:100%;
            display:block;
        }

        .container{
            width:min(var(--container), calc(100% - 48px));
            margin:auto;
        }

        /* =====================================================
           ADVANCED PAGE BACKGROUND
        ===================================================== */

        .page-background{
            position:fixed;
            inset:0;
            z-index:-18;
            pointer-events:none;
            overflow:hidden;
        }

        .page-background::before{
            content:"";
            position:absolute;
            width:780px;
            height:780px;
            left:-360px;
            top:-220px;

            background:
                radial-gradient(
                    circle,
                    rgba(249,115,22,.22) 0%,
                    rgba(249,115,22,.12) 28%,
                    rgba(249,115,22,.04) 52%,
                    transparent 72%
                );

            filter:blur(8px);
            animation:ambientFloat 12s ease-in-out infinite alternate;
        }

        .page-background::after{
            content:"";
            position:absolute;
            width:900px;
            height:900px;
            right:-430px;
            top:30px;

            background:
                radial-gradient(
                    circle,
                    rgba(234,88,12,.18) 0%,
                    rgba(249,115,22,.08) 32%,
                    transparent 72%
                );

            filter:blur(12px);
            animation:ambientFloat2 14s ease-in-out infinite alternate;
        }

        .page-grid{
            position:fixed;
            inset:0;
            z-index:-17;
            pointer-events:none;

            background-image:
                linear-gradient(
                    rgba(154,52,18,.055) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(154,52,18,.055) 1px,
                    transparent 1px
                );

            background-size:42px 42px;

            mask-image:
                linear-gradient(
                    to bottom,
                    rgba(0,0,0,.8),
                    transparent 90%
                );

            opacity:.45;
        }

        .page-dots{
            position:fixed;
            inset:0;
            z-index:-16;
            pointer-events:none;

            background-image:
                radial-gradient(
                    rgba(154,52,18,.25) 1px,
                    transparent 1px
                );

            background-size:20px 20px;

            mask-image:
                radial-gradient(
                    ellipse at center,
                    black 0%,
                    transparent 78%
                );

            opacity:.16;
        }

        /* =====================================================
           NAVBAR
        ===================================================== */

        .navbar{
            position:sticky;
            top:16px;
            z-index:1000;

            width:min(var(--container), calc(100% - 32px));

            margin:16px auto 0;

            display:flex;
            align-items:center;
            justify-content:space-between;

            padding:14px 18px;

            background:
                linear-gradient(
                    135deg,
                    rgba(255,255,255,.92),
                    rgba(255,248,239,.82)
                );

            backdrop-filter:blur(22px);
            -webkit-backdrop-filter:blur(22px);

            border:1px solid rgba(154,52,18,.12);
            border-radius:22px;

            box-shadow:
                0 12px 45px rgba(120,53,15,.10),
                inset 0 1px 0 rgba(255,255,255,.9);
        }

        .brand{
            display:flex;
            align-items:center;
            gap:12px;
        }

        .brand-mark{
            width:44px;
            height:44px;

            display:grid;
            place-items:center;

            border-radius:15px;

            color:#fff;

            font-weight:900;
            font-size:18px;

            background:
                linear-gradient(
                    145deg,
                    var(--orange-light),
                    var(--orange-main),
                    var(--orange-deep)
                );

            box-shadow:
                0 10px 25px rgba(234,88,12,.26),
                inset 0 1px 0 rgba(255,255,255,.4);
        }

        .brand-text{
            display:flex;
            flex-direction:column;
            line-height:1.05;
        }

        .brand-text strong{
            font-size:17px;
            letter-spacing:-.03em;
        }

        .brand-text span{
            margin-top:4px;
            color:var(--muted);
            font-size:10px;
            letter-spacing:.18em;
            text-transform:uppercase;
            font-weight:700;
        }

        .nav-links{
            display:flex;
            align-items:center;
            gap:8px;
        }

        .nav-links a{
            padding:10px 13px;
            color:var(--ink-soft);
            font-size:14px;
            font-weight:650;
            border-radius:12px;

            transition:
                background .25s ease,
                color .25s ease,
                transform .25s ease;
        }

        .nav-links a:hover{
            background:rgba(249,115,22,.08);
            color:var(--orange-dark);
            transform:translateY(-1px);
        }

        .nav-login{
            color:#fff !important;

            background:
                linear-gradient(
                    135deg,
                    var(--orange),
                    var(--orange-deep)
                ) !important;

            box-shadow:
                0 9px 22px rgba(234,88,12,.22);

            padding:11px 17px !important;
        }

        .nav-login:hover{
            color:#fff !important;
            transform:translateY(-2px) !important;
        }

        /* =====================================================
           HERO
        ===================================================== */

        .hero{
            position:relative;
            min-height:760px;

            display:grid;
            grid-template-columns:
                minmax(0, .92fr)
                minmax(520px, 1.08fr);

            align-items:center;

            gap:40px;
            padding:88px 0 65px;

            overflow:hidden;
        }

        /* cinematic rays */
        .hero::before{
            content:"";
            position:absolute;

            width:850px;
            height:850px;

            left:-300px;
            top:-330px;

            border-radius:50%;

            background:
                repeating-conic-gradient(
                    from 8deg,
                    rgba(249,115,22,.13) 0deg,
                    rgba(249,115,22,.13) 8deg,
                    transparent 8deg,
                    transparent 19deg
                );

            filter:blur(1px);

            opacity:.92;

            transform:rotate(-10deg);

            animation:
                rayRotate 28s linear infinite;
        }

        .hero::after{
            content:"";

            position:absolute;

            width:850px;
            height:850px;

            right:-300px;
            top:-170px;

            border-radius:50%;

            background:
                radial-gradient(
                    circle,
                    rgba(249,115,22,.18),
                    rgba(249,115,22,.08) 30%,
                    transparent 68%
                );

            filter:blur(20px);

            animation:
                heroGlow 9s ease-in-out infinite alternate;
        }

        .hero-content{
            position:relative;
            z-index:10;
        }

        .eyebrow{
            display:inline-flex;
            align-items:center;
            gap:9px;

            padding:8px 13px;

            border-radius:999px;

            background:rgba(255,255,255,.72);

            border:1px solid rgba(154,52,18,.13);

            color:var(--orange-dark);

            font-size:11px;
            font-weight:850;
            letter-spacing:.13em;
            text-transform:uppercase;

            box-shadow:
                0 8px 30px rgba(120,53,15,.06);
        }

        .eyebrow-dot{
            width:7px;
            height:7px;
            border-radius:50%;
            background:var(--orange);

            box-shadow:
                0 0 0 5px rgba(249,115,22,.12),
                0 0 18px rgba(249,115,22,.4);

            animation:pulseDot 2.2s ease-in-out infinite;
        }

        .hero-title{
            margin-top:23px;

            max-width:720px;

            font-size:
                clamp(
                    48px,
                    6vw,
                    86px
                );

            line-height:.98;

            letter-spacing:-.065em;

            font-weight:950;
        }

        .hero-title .dark{
            display:block;
            color:var(--ink);
        }

        .hero-title .gradient{
            display:inline-block;

            background:
                linear-gradient(
                    120deg,
                    var(--orange-deep) 0%,
                    var(--orange-main) 44%,
                    var(--orange-light) 100%
                );

            -webkit-background-clip:text;
            background-clip:text;
            color:transparent;

            filter:
                drop-shadow(
                    0 12px 18px rgba(234,88,12,.12)
                );
        }

        .hero-description{
            max-width:610px;
            margin-top:25px;

            color:var(--ink-soft);

            font-size:17px;
            line-height:1.8;
        }

        .hero-actions{
            display:flex;
            flex-wrap:wrap;
            gap:14px;
            margin-top:30px;
        }

        .btn{
            min-height:52px;

            padding:0 20px;

            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:10px;

            border-radius:15px;

            font-size:14px;
            font-weight:800;

            border:1px solid rgba(154,52,18,.12);

            transition:
                transform .28s ease,
                box-shadow .28s ease,
                background .28s ease;
        }

        .btn:hover{
            transform:translateY(-3px);
        }

        .btn-primary{
            color:#fff;

            background:
                linear-gradient(
                    135deg,
                    var(--orange-light),
                    var(--orange-main),
                    var(--orange-deep)
                );

            box-shadow:
                0 17px 35px rgba(234,88,12,.24),
                inset 0 1px 0 rgba(255,255,255,.35);
        }

        .btn-primary:hover{
            box-shadow:
                0 22px 45px rgba(234,88,12,.30),
                inset 0 1px 0 rgba(255,255,255,.38);
        }

        .btn-secondary{
            color:var(--orange-deep);
            background:rgba(255,255,255,.76);
        }

        .btn-secondary:hover{
            background:#fff;
            box-shadow:0 14px 35px rgba(120,53,15,.10);
        }

        .hero-stats{
            display:flex;
            flex-wrap:wrap;
            gap:24px;

            margin-top:42px;
        }

        .stat{
            display:flex;
            flex-direction:column;
            gap:5px;
        }

        .stat strong{
            font-size:19px;
            letter-spacing:-.03em;
        }

        .stat span{
            color:var(--muted);
            font-size:12px;
        }

        /* =====================================================
           3D HERO VISUAL
        ===================================================== */

        .hero-visual{
            position:relative;
            min-height:700px;

            display:grid;
            place-items:center;

            z-index:8;

            perspective:1500px;
        }

        .visual-shadow{
            position:absolute;

            width:330px;
            height:85px;

            left:50%;
            bottom:90px;

            transform:
                translateX(-50%)
                translateZ(-100px);

            border-radius:50%;

            background:
                radial-gradient(
                    ellipse,
                    rgba(154,52,18,.28),
                    rgba(154,52,18,.08) 40%,
                    transparent 75%
                );

            filter:blur(18px);

            z-index:1;
        }

        .orbit-scene{
            position:relative;

            width:min(610px, 92vw);
            aspect-ratio:1;

            transform-style:preserve-3d;

            animation:
                sceneFloat 7s ease-in-out infinite;
        }

        .orbit-glow{
            position:absolute;
            inset:12%;

            border-radius:50%;

            background:
                radial-gradient(
                    circle,
                    rgba(249,115,22,.26),
                    rgba(249,115,22,.08) 38%,
                    transparent 70%
                );

            filter:blur(12px);

            transform:translateZ(-80px);

            animation:coreGlow 4s ease-in-out infinite alternate;
        }

        .ring{
            position:absolute;

            left:50%;
            top:50%;

            border-radius:50%;

            border:
                1px solid rgba(194,65,12,.23);

            transform-style:preserve-3d;

            box-shadow:
                0 0 30px rgba(249,115,22,.06),
                inset 0 0 20px rgba(249,115,22,.04);
        }

        .ring::after{
            content:"";

            position:absolute;

            width:8px;
            height:8px;

            top:10%;
            left:24%;

            border-radius:50%;

            background:
                radial-gradient(
                    circle,
                    #fff,
                    var(--orange-light) 34%,
                    var(--orange-deep)
                );

            box-shadow:
                0 0 0 6px rgba(249,115,22,.08),
                0 0 25px rgba(249,115,22,.35);
        }

        .ring-one{
            width:78%;
            aspect-ratio:1;

            transform:
                translate(-50%,-50%)
                rotateX(66deg)
                rotateY(-14deg);

            animation:
                ringSpin1 18s linear infinite;
        }

        .ring-two{
            width:92%;
            aspect-ratio:1;

            border-color:rgba(249,115,22,.17);

            transform:
                translate(-50%,-50%)
                rotateY(70deg)
                rotateX(16deg);

            animation:
                ringSpin2 24s linear infinite reverse;
        }

        .ring-three{
            width:64%;
            aspect-ratio:1;

            border-style:dashed;
            border-color:rgba(194,65,12,.18);

            transform:
                translate(-50%,-50%)
                rotateX(35deg)
                rotateZ(18deg);

            animation:
                ringSpin3 13s linear infinite;
        }

        /* central sphere */

        .core{
            position:absolute;

            left:50%;
            top:50%;

            width:285px;
            aspect-ratio:1;

            transform:
                translate(-50%,-50%)
                translateZ(110px);

            border-radius:50%;

            background:
                radial-gradient(
                    circle at 31% 25%,
                    #fff8ed 0%,
                    #fed7aa 18%,
                    #fb923c 44%,
                    #ea580c 69%,
                    #9a3412 100%
                );

            box-shadow:
                0 40px 90px rgba(154,52,18,.25),
                0 0 120px rgba(249,115,22,.20),
                inset -28px -35px 55px rgba(120,53,15,.24),
                inset 18px 18px 40px rgba(255,255,255,.32);

            display:grid;
            place-items:center;

            animation:
                sphereFloat 5s ease-in-out infinite;
        }

        .core::before{
            content:"";

            position:absolute;
            inset:11%;

            border-radius:50%;

            border:
                1px solid rgba(255,255,255,.42);

            box-shadow:
                inset 0 0 25px rgba(255,255,255,.18);
        }

        .core::after{
            content:"";

            position:absolute;

            top:16%;
            left:18%;

            width:29%;
            height:17%;

            border-radius:50%;

            background:
                linear-gradient(
                    135deg,
                    rgba(255,255,255,.85),
                    rgba(255,255,255,0)
                );

            filter:blur(2px);

            transform:rotate(-20deg);
        }

        .core-inner{
            position:relative;
            z-index:2;

            display:flex;
            flex-direction:column;
            align-items:center;
            text-align:center;

            color:#fff;

            text-shadow:
                0 4px 14px rgba(120,53,15,.30);
        }

        .core-icon{
            width:60px;
            height:60px;

            display:grid;
            place-items:center;

            margin-bottom:12px;

            border-radius:20px;

            background:
                rgba(255,255,255,.16);

            border:
                1px solid rgba(255,255,255,.32);

            backdrop-filter:blur(10px);

            font-size:30px;

            box-shadow:
                inset 0 1px 0 rgba(255,255,255,.34);
        }

        .core-title{
            font-size:37px;
            font-weight:950;
            letter-spacing:-.055em;
        }

        .core-sub{
            margin-top:5px;

            font-size:9px;
            letter-spacing:.27em;
            font-weight:800;

            opacity:.9;
        }

        /* =====================================================
           FLOATING PORTAL NODES
        ===================================================== */

        .node{
            position:absolute;

            min-width:145px;

            padding:13px 16px;

            border-radius:17px;

            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.94),
                    rgba(255,247,237,.78)
                );

            backdrop-filter:blur(16px);
            -webkit-backdrop-filter:blur(16px);

            border:
                1px solid rgba(154,52,18,.13);

            box-shadow:
                0 20px 45px rgba(120,53,15,.11),
                inset 0 1px 0 rgba(255,255,255,.85);

            display:flex;
            align-items:center;
            gap:10px;

            color:var(--ink);

            font-size:12px;
            font-weight:850;

            z-index:20;

            animation:
                nodeFloat 5s ease-in-out infinite;
        }

        .node::before{
            content:"";

            width:8px;
            height:8px;

            flex:0 0 auto;

            border-radius:50%;

            background:
                linear-gradient(
                    135deg,
                    var(--orange-light),
                    var(--orange-deep)
                );

            box-shadow:
                0 0 0 5px rgba(249,115,22,.08),
                0 0 20px rgba(249,115,22,.25);
        }

        .node-one{
            top:8%;
            left:4%;
            animation-delay:-.8s;
        }

        .node-two{
            top:24%;
            right:2%;
            animation-delay:-2s;
        }

        .node-three{
            right:3%;
            bottom:17%;
            animation-delay:-3.3s;
        }

        .node-four{
            left:4%;
            bottom:19%;
            animation-delay:-1.6s;
        }

        .node-five{
            left:50%;
            top:1%;
            transform:translateX(-50%);
            animation-delay:-4s;
        }

        /* =====================================================
           BACKGROUND FLOATING SHAPES
        ===================================================== */

        .bg-orb{
            position:absolute;

            border-radius:50%;

            pointer-events:none;

            opacity:.8;

            filter:blur(.2px);

            z-index:1;
        }

        .bg-orb-one{
            width:160px;
            height:160px;

            top:18%;
            left:44%;

            background:
                radial-gradient(
                    circle at 30% 30%,
                    rgba(255,255,255,.9),
                    rgba(249,115,22,.16) 45%,
                    rgba(154,52,18,.06) 72%,
                    transparent 75%
                );

            box-shadow:
                0 25px 80px rgba(234,88,12,.08);

            animation:
                bgOrbOne 11s ease-in-out infinite;
        }

        .bg-orb-two{
            width:95px;
            height:95px;

            bottom:15%;
            left:34%;

            background:
                radial-gradient(
                    circle at 35% 30%,
                    rgba(255,255,255,.9),
                    rgba(249,115,22,.16),
                    transparent 73%
                );

            animation:
                bgOrbTwo 8s ease-in-out infinite;
        }

        .bg-orb-three{
            width:230px;
            height:230px;

            top:45%;
            right:16%;

            background:
                radial-gradient(
                    circle,
                    rgba(249,115,22,.08),
                    transparent 70%
                );

            border:1px solid rgba(194,65,12,.05);

            animation:
                bgOrbThree 16s ease-in-out infinite;
        }

        .bg-line{
            position:absolute;

            height:1px;

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(194,65,12,.12),
                    transparent
                );

            transform-origin:center;
        }

        .bg-line-one{
            width:520px;
            left:-80px;
            top:50%;
            transform:rotate(-14deg);
        }

        .bg-line-two{
            width:450px;
            right:-60px;
            top:37%;
            transform:rotate(19deg);
        }

        /* =====================================================
           SERVICES
        ===================================================== */

        .services{
            position:relative;
            padding:92px 0 100px;
        }

        .services::before{
            content:"";

            position:absolute;

            width:650px;
            height:350px;

            left:-220px;
            top:20%;

            border-radius:50%;

            background:
                radial-gradient(
                    circle,
                    rgba(249,115,22,.12),
                    transparent 70%
                );

            filter:blur(20px);

            pointer-events:none;
        }

        .services::after{
            content:"";

            position:absolute;

            width:650px;
            height:350px;

            right:-240px;
            bottom:0;

            border-radius:50%;

            background:
                radial-gradient(
                    circle,
                    rgba(234,88,12,.09),
                    transparent 72%
                );

            filter:blur(20px);

            pointer-events:none;
        }

        .section-heading{
            position:relative;
            z-index:2;

            max-width:720px;
            margin-bottom:38px;
        }

        .section-kicker{
            color:var(--orange-dark);

            font-size:11px;
            font-weight:900;
            letter-spacing:.18em;
            text-transform:uppercase;
        }

        .section-title{
            margin-top:10px;

            font-size:
                clamp(
                    34px,
                    4.5vw,
                    58px
                );

            line-height:1.03;

            letter-spacing:-.055em;

            font-weight:950;
        }

        .section-description{
            margin-top:14px;

            max-width:650px;

            color:var(--ink-soft);

            font-size:15px;
            line-height:1.75;
        }

        .service-grid{
            position:relative;
            z-index:3;

            display:grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap:18px;
        }

        .system-admin-card{
            grid-column:2 / span 2;
        }

        .service-card{
            position:relative;

            min-height:310px;

            padding:24px;

            border-radius:25px;

            overflow:hidden;

            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.93),
                    rgba(255,247,237,.78)
                );

            border:
                1px solid rgba(154,52,18,.11);

            box-shadow:
                0 20px 50px rgba(120,53,15,.08),
                inset 0 1px 0 rgba(255,255,255,.86);

            transform-style:preserve-3d;

            transition:
                transform .42s cubic-bezier(.2,.8,.2,1),
                box-shadow .42s ease,
                border-color .42s ease;
        }

        .service-card::before{
            content:"";

            position:absolute;

            inset:0;

            background:
                radial-gradient(
                    circle at 100% 0%,
                    rgba(249,115,22,.15),
                    transparent 32%
                );

            pointer-events:none;
        }

        .service-card::after{
            content:"";

            position:absolute;

            width:210px;
            height:210px;

            right:-85px;
            bottom:-90px;

            border-radius:50%;

            background:
                radial-gradient(
                    circle,
                    rgba(249,115,22,.12),
                    transparent 70%
                );
        }

        .service-card:hover{
            transform:
                translateY(-10px)
                rotateX(3deg)
                rotateY(-3deg);

            box-shadow:
                0 35px 75px rgba(120,53,15,.14),
                inset 0 1px 0 rgba(255,255,255,.95);

            border-color:
                rgba(194,65,12,.20);
        }

        .service-icon{
            position:relative;
            z-index:2;

            width:58px;
            height:58px;

            display:grid;
            place-items:center;

            border-radius:18px;

            background:
                linear-gradient(
                    145deg,
                    #fff6ea,
                    #fed7aa
                );

            border:
                1px solid rgba(234,88,12,.14);

            color:var(--orange-dark);

            font-size:23px;

            box-shadow:
                inset 0 1px 0 rgba(255,255,255,.8),
                0 12px 28px rgba(234,88,12,.08);
        }

        .service-card h3{
            position:relative;
            z-index:2;

            margin-top:22px;

            font-size:21px;
            letter-spacing:-.035em;
        }

        .service-card p{
            position:relative;
            z-index:2;

            margin-top:10px;

            color:var(--ink-soft);

            line-height:1.7;
            font-size:13px;
        }

        .service-link{
            position:absolute;
            left:24px;
            bottom:24px;
            z-index:3;

            display:inline-flex;
            align-items:center;
            gap:8px;

            color:var(--orange-dark);

            font-size:12px;
            font-weight:900;
            letter-spacing:.01em;

            transition:
                gap .25s ease;
        }

        .service-card:hover .service-link{
            gap:12px;
        }

        /* =====================================================
           COMMUNITY SECTION
        ===================================================== */

        .community{
            padding:60px 0 110px;
        }

        .community-shell{
            position:relative;

            padding:38px;

            border-radius:34px;

            overflow:hidden;

            background:
                linear-gradient(
                    140deg,
                    #fffdfb,
                    #fff5e9 58%,
                    #ffead5
                );

            border:
                1px solid rgba(154,52,18,.12);

            box-shadow:
                0 30px 85px rgba(120,53,15,.11);
        }

        .community-shell::before{
            content:"";

            position:absolute;

            width:540px;
            height:540px;

            right:-150px;
            top:-230px;

            border-radius:50%;

            background:
                radial-gradient(
                    circle,
                    rgba(249,115,22,.18),
                    transparent 69%
                );

            filter:blur(4px);
        }

        .community-grid{
            position:relative;
            z-index:2;

            display:grid;
            grid-template-columns:
                1.05fr
                .95fr;

            gap:35px;

            align-items:center;
        }

        .community-copy h2{
            margin-top:12px;

            font-size:
                clamp(
                    35px,
                    4vw,
                    58px
                );

            line-height:1.02;

            letter-spacing:-.055em;
        }

        .community-copy p{
            max-width:600px;

            margin-top:16px;

            color:var(--ink-soft);

            line-height:1.8;
            font-size:15px;
        }

        .community-badge{
            display:inline-flex;
            align-items:center;
            gap:9px;

            padding:8px 13px;

            border-radius:999px;

            background:#fff;

            border:
                1px solid rgba(154,52,18,.12);

            color:var(--orange-dark);

            font-size:10px;
            font-weight:900;
            letter-spacing:.12em;
            text-transform:uppercase;
        }

        .mock-post{
            position:relative;

            padding:22px;

            border-radius:24px;

            background:
                rgba(255,255,255,.78);

            border:
                1px solid rgba(154,52,18,.10);

            box-shadow:
                0 25px 55px rgba(120,53,15,.10);

            backdrop-filter:blur(14px);
        }

        .mock-post-head{
            display:flex;
            align-items:center;
            gap:12px;
        }

        .avatar{
            width:45px;
            height:45px;

            display:grid;
            place-items:center;

            border-radius:14px;

            color:#fff;
            font-weight:900;

            background:
                linear-gradient(
                    135deg,
                    var(--orange),
                    var(--orange-deep)
                );
        }

        .mock-post-head div:last-child{
            display:flex;
            flex-direction:column;
            gap:3px;
        }

        .mock-post-head strong{
            font-size:13px;
        }

        .mock-post-head span{
            color:var(--muted);
            font-size:11px;
        }

        .mock-post-body{
            margin-top:20px;

            padding:18px;

            border-radius:18px;

            background:
                linear-gradient(
                    145deg,
                    #fffaf4,
                    #ffeedc
                );

            border:1px solid rgba(154,52,18,.08);
        }

        .mock-label{
            display:inline-flex;

            padding:6px 9px;

            border-radius:8px;

            color:var(--orange-dark);

            background:rgba(249,115,22,.10);

            font-size:10px;
            font-weight:900;
        }

        .mock-post-body h4{
            margin-top:12px;
            font-size:17px;
        }

        .mock-post-body p{
            margin-top:7px;
            font-size:12px;
            line-height:1.65;
            color:var(--ink-soft);
        }

        .mock-post-actions{
            margin-top:17px;

            display:flex;
            align-items:center;
            justify-content:space-between;

            color:var(--muted);

            font-size:11px;
            font-weight:700;
        }

        .community-actions{
            display:flex;
            flex-wrap:wrap;
            gap:12px;
            margin-top:27px;
        }

        /* =====================================================
           FLOW SECTION
        ===================================================== */

        .flow-section{
            padding:25px 0 110px;
        }

        .flow-wrap{
            display:grid;
            grid-template-columns:
                .95fr
                1.05fr;

            gap:50px;

            align-items:center;
        }

        .flow-copy h2{
            margin-top:11px;

            font-size:
                clamp(
                    36px,
                    4vw,
                    56px
                );

            line-height:1.02;

            letter-spacing:-.055em;
        }

        .flow-copy p{
            margin-top:15px;

            max-width:550px;

            color:var(--ink-soft);

            line-height:1.8;
            font-size:15px;
        }

        .flow-steps{
            display:grid;
            gap:14px;
        }

        .flow-step{
            display:grid;
            grid-template-columns:52px 1fr;
            gap:16px;
            align-items:center;

            padding:17px;

            border-radius:19px;

            background:
                rgba(255,255,255,.68);

            border:
                1px solid rgba(154,52,18,.10);

            box-shadow:
                0 15px 35px rgba(120,53,15,.06);

            transition:
                transform .3s ease,
                box-shadow .3s ease;
        }

        .flow-step:hover{
            transform:translateX(6px);
            box-shadow:
                0 20px 45px rgba(120,53,15,.10);
        }

        .step-number{
            width:52px;
            height:52px;

            display:grid;
            place-items:center;

            border-radius:16px;

            color:var(--orange-deep);

            background:
                linear-gradient(
                    145deg,
                    #fff7ed,
                    #fed7aa
                );

            border:
                1px solid rgba(234,88,12,.12);

            font-weight:950;
        }

        .flow-step strong{
            font-size:14px;
        }

        .flow-step p{
            margin-top:4px;
            color:var(--muted);
            font-size:12px;
            line-height:1.55;
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        .footer{
            padding:28px 0 35px;

            border-top:
                1px solid rgba(154,52,18,.09);
        }

        .footer-inner{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:20px;
        }

        .footer-copy{
            color:var(--muted);
            font-size:12px;
        }

        .footer-links{
            display:flex;
            gap:18px;
            color:var(--muted);
            font-size:12px;
        }

        .footer-links a:hover{
            color:var(--orange-dark);
        }

        /* =====================================================
           SCROLL REVEAL
        ===================================================== */

        .reveal{
            opacity:0;
            transform:translateY(30px);
            transition:
                opacity .8s ease,
                transform .8s cubic-bezier(.2,.8,.2,1);
        }

        .reveal.show{
            opacity:1;
            transform:none;
        }

        /* =====================================================
           MOUSE SPOTLIGHT
        ===================================================== */

        .cursor-light{
            position:fixed;

            width:340px;
            height:340px;

            left:0;
            top:0;

            border-radius:50%;

            pointer-events:none;
            z-index:9999;

            transform:
                translate(-50%,-50%);

            background:
                radial-gradient(
                    circle,
                    rgba(249,115,22,.10),
                    rgba(249,115,22,.04) 35%,
                    transparent 70%
                );

            filter:blur(10px);

            opacity:.75;
        }

        /* =====================================================
           ANIMATIONS
        ===================================================== */

        @keyframes ambientFloat{
            from{
                transform:
                    translate3d(0,0,0)
                    scale(1);
            }
            to{
                transform:
                    translate3d(70px,45px,0)
                    scale(1.08);
            }
        }

        @keyframes ambientFloat2{
            from{
                transform:
                    translate3d(0,0,0)
                    scale(1);
            }
            to{
                transform:
                    translate3d(-65px,35px,0)
                    scale(1.1);
            }
        }

        @keyframes rayRotate{
            from{
                transform:rotate(-10deg);
            }
            to{
                transform:rotate(350deg);
            }
        }

        @keyframes heroGlow{
            from{
                transform:scale(.95);
                opacity:.7;
            }
            to{
                transform:scale(1.1);
                opacity:1;
            }
        }

        @keyframes pulseDot{
            0%,100%{
                box-shadow:
                    0 0 0 5px rgba(249,115,22,.12),
                    0 0 18px rgba(249,115,22,.4);
            }
            50%{
                box-shadow:
                    0 0 0 10px rgba(249,115,22,.04),
                    0 0 24px rgba(249,115,22,.6);
            }
        }

        @keyframes sceneFloat{
            0%,100%{
                transform:
                    translateY(0px)
                    rotateX(0deg)
                    rotateY(0deg);
            }
            50%{
                transform:
                    translateY(-12px)
                    rotateX(2deg)
                    rotateY(-2deg);
            }
        }

        @keyframes ringSpin1{
            from{
                transform:
                    translate(-50%,-50%)
                    rotateX(66deg)
                    rotateY(-14deg)
                    rotateZ(0deg);
            }
            to{
                transform:
                    translate(-50%,-50%)
                    rotateX(66deg)
                    rotateY(-14deg)
                    rotateZ(360deg);
            }
        }

        @keyframes ringSpin2{
            from{
                transform:
                    translate(-50%,-50%)
                    rotateY(70deg)
                    rotateX(16deg)
                    rotateZ(0deg);
            }
            to{
                transform:
                    translate(-50%,-50%)
                    rotateY(70deg)
                    rotateX(16deg)
                    rotateZ(360deg);
            }
        }

        @keyframes ringSpin3{
            from{
                transform:
                    translate(-50%,-50%)
                    rotateX(35deg)
                    rotateZ(18deg)
                    rotateZ(0deg);
            }
            to{
                transform:
                    translate(-50%,-50%)
                    rotateX(35deg)
                    rotateZ(378deg);
            }
        }

        @keyframes sphereFloat{
            0%,100%{
                transform:
                    translate(-50%,-50%)
                    translateZ(110px)
                    translateY(0);
            }
            50%{
                transform:
                    translate(-50%,-50%)
                    translateZ(110px)
                    translateY(-10px);
            }
        }

        @keyframes coreGlow{
            from{
                transform:
                    translateZ(-80px)
                    scale(.95);
                opacity:.7;
            }
            to{
                transform:
                    translateZ(-80px)
                    scale(1.08);
                opacity:1;
            }
        }

        @keyframes nodeFloat{
            0%,100%{
                transform:translateY(0);
            }
            50%{
                transform:translateY(-8px);
            }
        }

        @keyframes bgOrbOne{
            0%,100%{
                transform:
                    translate3d(0,0,0)
                    rotate(0deg);
            }
            50%{
                transform:
                    translate3d(55px,-30px,0)
                    rotate(35deg);
            }
        }

        @keyframes bgOrbTwo{
            0%,100%{
                transform:
                    translate3d(0,0,0)
                    scale(1);
            }
            50%{
                transform:
                    translate3d(-30px,-55px,0)
                    scale(1.12);
            }
        }

        @keyframes bgOrbThree{
            0%,100%{
                transform:
                    translate3d(0,0,0)
                    rotate(0deg);
            }
            50%{
                transform:
                    translate3d(-60px,35px,0)
                    rotate(25deg);
            }
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width:1100px){

            .service-grid{
                grid-template-columns:
                    repeat(2, minmax(0,1fr));
            }

            .system-admin-card{
                grid-column:1 / -1;
            }

            .hero{
                grid-template-columns:1fr;
                min-height:auto;
                padding-top:75px;
            }

            .hero-content{
                max-width:850px;
            }

            .hero-visual{
                min-height:600px;
            }

            .community-grid,
            .flow-wrap{
                grid-template-columns:1fr;
            }
        }

        @media (max-width:760px){

            .container{
                width:min(
                    var(--container),
                    calc(100% - 28px)
                );
            }

            .navbar{
                width:calc(100% - 20px);
                top:10px;
                margin-top:10px;
                padding:10px 12px;
                border-radius:18px;
            }

            .brand-mark{
                width:39px;
                height:39px;
                border-radius:12px;
            }

            .brand-text span{
                display:none;
            }

            .nav-links{
                gap:2px;
            }

            .nav-links a{
                padding:9px 8px;
                font-size:11px;
            }

            .nav-links a:not(.nav-login){
                display:none;
            }

            .nav-login{
                padding:9px 12px !important;
            }

            .hero{
                padding:
                    58px 0
                    45px;
            }

            .hero-title{
                font-size:
                    clamp(
                        43px,
                        13vw,
                        70px
                    );
            }

            .hero-description{
                font-size:15px;
            }

            .hero-actions{
                flex-direction:column;
            }

            .btn{
                width:100%;
            }

            .hero-stats{
                gap:18px;
            }

            .hero-visual{
                min-height:480px;
                margin-top:15px;
            }

            .orbit-scene{
                width:92vw;
            }

            .core{
                width:205px;
            }

            .core-title{
                font-size:28px;
            }

            .core-icon{
                width:48px;
                height:48px;
                border-radius:15px;
                font-size:23px;
            }

            .node{
                min-width:112px;
                padding:9px 11px;
                border-radius:13px;
                font-size:9px;
            }

            .node-one{
                top:12%;
                left:0;
            }

            .node-two{
                top:27%;
                right:0;
            }

            .node-three{
                right:0;
                bottom:15%;
            }

            .node-four{
                left:0;
                bottom:18%;
            }

            .node-five{
                top:3%;
            }

            .services,
            .community,
            .flow-section{
                padding-bottom:75px;
            }

            .service-grid{
                grid-template-columns:1fr;
            }

            .service-card{
                min-height:270px;
            }

            .community-shell{
                padding:24px;
                border-radius:25px;
            }

            .footer-inner{
                flex-direction:column;
                align-items:flex-start;
            }

            .cursor-light{
                display:none;
            }

            .page-grid{
                background-size:30px 30px;
            }
        }

        @media (prefers-reduced-motion:reduce){

            *,
            *::before,
            *::after{
                animation-duration:.01ms !important;
                animation-iteration-count:1 !important;
                transition-duration:.01ms !important;
                scroll-behavior:auto !important;
            }
        }
    </style>
<link rel="stylesheet" href="css/buttons.css">
</head>

<body>

    <!-- Advanced Background Layers -->
    <div class="page-background"></div>
    <div class="page-grid"></div>
    <div class="page-dots"></div>

    <div class="cursor-light" id="cursorLight"></div>

    <!-- =====================================================
         NAVBAR
    ====================================================== -->

    <nav class="navbar">

        <a href="index.php" class="brand">

            <div class="brand-mark">
                UF
            </div>

            <div class="brand-text">
                <strong>UniFlow</strong>
                <span>Smart Campus Platform</span>
            </div>

        </a>

        <div class="nav-links">

            <a href="#services">
                Services
            </a>

            <a href="#community">
                Community
            </a>

            <a href="lost_found/index.php">
                Lost &amp; Found
            </a>

            <a href="login.php" class="nav-login">
                Login
            </a>

        </div>

    </nav>


    <!-- =====================================================
         HERO
    ====================================================== -->

    <main>

        <section class="hero">

            <!-- Floating Background Decorative Shapes -->

            <div class="bg-orb bg-orb-one"></div>
            <div class="bg-orb bg-orb-two"></div>
            <div class="bg-orb bg-orb-three"></div>

            <div class="bg-line bg-line-one"></div>
            <div class="bg-line bg-line-two"></div>


            <!-- LEFT -->

            <div class="container" style="display:contents;">

                <div class="hero-content reveal">

                    <div class="eyebrow">
                        <span class="eyebrow-dot"></span>
                        Intelligent Campus Experience
                    </div>

                    <h1 class="hero-title">

                        <span class="dark">
                            One Campus.
                        </span>

                        <span class="gradient">
                            One Smart Flow.
                        </span>

                    </h1>

                    <p class="hero-description">
                        UniFlow connects students, university services and
                        the campus community in one smart platform for
                        reporting, managing, tracking and reconnecting.
                    </p>

                    <div class="hero-actions">

                        <a
                            href="login.php"
                            class="btn btn-primary"
                        >
                            Login to UniFlow
                            <span>→</span>
                        </a>

                        <a
                            href="#services"
                            class="btn btn-secondary"
                        >
                            Explore Services
                            <span>↘</span>
                        </a>

                    </div>

                    <div class="hero-stats">

                        <div class="stat">
                            <strong>4</strong>
                            <span>Campus Access Areas</span>
                        </div>

                        <div class="stat">
                            <strong>1</strong>
                            <span>Unified Platform</span>
                        </div>

                        <div class="stat">
                            <strong>24/7</strong>
                            <span>Community Access</span>
                        </div>

                    </div>

                </div>


                <!-- RIGHT 3D VISUAL -->

                <div class="hero-visual">

                    <div class="visual-shadow"></div>

                    <div class="orbit-scene">

                        <div class="orbit-glow"></div>


                        <!-- Orbit Rings -->

                        <div class="ring ring-one"></div>

                        <div class="ring ring-two"></div>

                        <div class="ring ring-three"></div>


                        <!-- Central Core -->

                        <div class="core">

                            <div class="core-inner">

                                <div class="core-icon">
                                    ◈
                                </div>

                                <div class="core-title">
                                    UniFlow
                                </div>

                                <div class="core-sub">
                                    SMART CAMPUS CORE
                                </div>

                            </div>

                        </div>


                        <!-- Floating Service Nodes -->

                        <div class="node node-one">
                            Technical
                        </div>

                        <div class="node node-two">
                            Administrative
                        </div>

                        <div class="node node-three">
                            Proctorial
                        </div>

                        <div class="node node-four">
                            Lost &amp; Found
                        </div>

                        <div class="node node-five">
                            Campus Connect
                        </div>

                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             SERVICES
        ====================================================== -->

        <section
            class="services"
            id="services"
        >

            <div class="container">

                <div class="section-heading reveal">

                    <div class="section-kicker">
                        Campus Portals
                    </div>

                    <h2 class="section-title">
                        Everything you need,
                        <br>
                        in one flow.
                    </h2>

                    <p class="section-description">
                        Access the university's operational portals
                        through one connected experience built around
                        students, services and the wider campus community.
                    </p>

                </div>


                <div class="service-grid">

                    <!-- Technical -->

                    <a
                        href="login.php?role=technical"
                        class="service-card reveal"
                    >

                        <div class="service-icon">
                            ⚙
                        </div>

                        <h3>
                            Technical
                        </h3>

                        <p>
                            Manage technical services, requests,
                            maintenance workflows and campus support
                            operations through the technical portal.
                        </p>

                        <div class="service-link">
                            Open Portal
                            <span>→</span>
                        </div>

                    </a>


                    <!-- Administrative -->

                    <a
                        href="login.php?role=administrative"
                        class="service-card reveal"
                    >

                        <div class="service-icon">
                            ◫
                        </div>

                        <h3>
                            Administrative
                        </h3>

                        <p>
                            Handle administrative requests,
                            student services and operational processes
                            from one centralized portal.
                        </p>

                        <div class="service-link">
                            Open Portal
                            <span>→</span>
                        </div>

                    </a>


                    <!-- Proctorial -->

                    <a
                        href="login.php?role=proctorial"
                        class="service-card reveal"
                    >

                        <div class="service-icon">
                            ◉
                        </div>

                        <h3>
                            Proctorial
                        </h3>

                        <p>
                            Manage campus discipline, student safety,
                            incident workflows and proctorial
                            responsibilities efficiently.
                        </p>

                        <div class="service-link">
                            Open Portal
                            <span>→</span>
                        </div>

                    </a>


                    <!-- Lost & Found -->

                    <a
                        href="lost_found/index.php"
                        class="service-card reveal"
                    >

                        <div class="service-icon">
                            ♢
                        </div>

                        <h3>
                            Lost &amp; Found
                        </h3>

                        <p>
                            Discover community posts, report missing
                            belongings and reconnect lost items with
                            their owners through the campus community.
                        </p>

                        <div class="service-link">
                            Visit Community
                            <span>→</span>
                        </div>

                    </a>

                    <?php if (($_SESSION["admin_type"] ?? "admin") === "main_admin"): ?>
                    <a
                        href="system_admin/dashboard.php"
                        class="service-card reveal system-admin-card"
                    >
                        <div class="service-icon">⚙</div>
                        <h3>System Admin</h3>
                        <p>Manage student and admin accounts, access control, invitations and UniFlow settings.</p>
                        <div class="service-link">Open System Admin <span>→</span></div>
                    </a>
                    <?php endif; ?>

                </div>

            </div>

        </section>


        <!-- =====================================================
             COMMUNITY
        ====================================================== -->

        <section
            class="community"
            id="community"
        >

            <div class="container">

                <div class="community-shell reveal">

                    <div class="community-grid">

                        <div class="community-copy">

                            <div class="community-badge">
                                ✦ Community Hub
                            </div>

                            <h2>
                                The campus
                                <span class="gradient">
                                    talks here.
                                </span>
                            </h2>

                            <p>
                                Lost &amp; Found is designed as a
                                public community experience where
                                students can discover posts, share
                                information and help reconnect missing
                                belongings.
                            </p>

                            <div class="community-actions">

                                <a
                                    href="lost_found/index.php"
                                    class="btn btn-primary"
                                >
                                    Explore Lost &amp; Found
                                    <span>→</span>
                                </a>

                                <a
                                    href="login.php?return_to=lost_found"
                                    class="btn btn-secondary"
                                >
                                    Join the Community
                                </a>

                            </div>

                        </div>


                        <!-- Mock Community Card -->

                        <div class="mock-post">

                            <div class="mock-post-head">

                                <div class="avatar">
                                    UF
                                </div>

                                <div>
                                    <strong>
                                        Campus Community
                                    </strong>

                                    <span>
                                        Lost &amp; Found
                                    </span>
                                </div>

                            </div>


                            <div class="mock-post-body">

                                <span class="mock-label">
                                    LOST ITEM
                                </span>

                                <h4>
                                    Black Wallet
                                </h4>

                                <p>
                                    A black wallet was reportedly
                                    lost around the central campus
                                    area. Community members can help
                                    share information and reconnect
                                    the item with its owner.
                                </p>

                            </div>


                            <div class="mock-post-actions">

                                <span>
                                    ♡ 14 likes
                                </span>

                                <span>
                                    6 comments
                                </span>

                                <span>
                                    ↗ Share
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             FLOW
        ====================================================== -->

        <section class="flow-section">

            <div class="container">

                <div class="flow-wrap">

                    <div class="flow-copy reveal">

                        <div class="section-kicker">
                            How UniFlow Works
                        </div>

                        <h2>
                            Report.
                            Track.
                            Resolve.
                            <span class="gradient">
                                Connect.
                            </span>
                        </h2>

                        <p>
                            UniFlow brings operational portals and
                            community interaction into a single
                            connected campus experience.
                        </p>

                    </div>


                    <div class="flow-steps">

                        <div class="flow-step reveal">

                            <div class="step-number">
                                01
                            </div>

                            <div>
                                <strong>
                                    Access
                                </strong>

                                <p>
                                    Choose the right campus service
                                    or community area.
                                </p>
                            </div>

                        </div>


                        <div class="flow-step reveal">

                            <div class="step-number">
                                02
                            </div>

                            <div>
                                <strong>
                                    Report
                                </strong>

                                <p>
                                    Submit information, requests
                                    or community posts.
                                </p>
                            </div>

                        </div>


                        <div class="flow-step reveal">

                            <div class="step-number">
                                03
                            </div>

                            <div>
                                <strong>
                                    Track
                                </strong>

                                <p>
                                    Follow progress and stay
                                    connected with the process.
                                </p>
                            </div>

                        </div>


                        <div class="flow-step reveal">

                            <div class="step-number">
                                04
                            </div>

                            <div>
                                <strong>
                                    Resolve
                                </strong>

                                <p>
                                    Complete the workflow and
                                    reconnect the campus community.
                                </p>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>

    </main>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <footer class="footer">

        <div class="container">

            <div class="footer-inner">

                <div class="footer-copy">
                    © <?php echo date("Y"); ?> UniFlow.
                    One Campus. One Smart Flow.
                </div>

                <div class="footer-links">

                    <a href="index.php">
                        Home
                    </a>

                    <a href="#services">
                        Services
                    </a>

                    <a href="lost_found/index.php">
                        Community
                    </a>

                    <a href="login.php">
                        Login
                    </a>

                </div>

            </div>

        </div>

    </footer>


    <!-- =====================================================
         JAVASCRIPT
    ====================================================== -->

    <script>

        /* =====================================================
           MOUSE FOLLOW SPOTLIGHT
        ====================================================== */

        const cursorLight =
            document.getElementById("cursorLight");

        let mouseX = window.innerWidth / 2;
        let mouseY = window.innerHeight / 2;

        let currentX = mouseX;
        let currentY = mouseY;

        document.addEventListener("mousemove", function(e){

            mouseX = e.clientX;
            mouseY = e.clientY;

        });

        function animateCursor(){

            currentX += (mouseX - currentX) * 0.08;
            currentY += (mouseY - currentY) * 0.08;

            cursorLight.style.left = currentX + "px";
            cursorLight.style.top = currentY + "px";

            requestAnimationFrame(animateCursor);

        }

        animateCursor();


        /* =====================================================
           SCROLL REVEAL
        ====================================================== */

        const revealItems =
            document.querySelectorAll(".reveal");

        const revealObserver =
            new IntersectionObserver(
                function(entries){

                    entries.forEach(function(entry){

                        if(entry.isIntersecting){

                            entry.target.classList.add("show");

                            revealObserver.unobserve(
                                entry.target
                            );

                        }

                    });

                },
                {
                    threshold:0.12
                }
            );

        revealItems.forEach(function(item){

            revealObserver.observe(item);

        });


        /* =====================================================
           HERO 3D MOUSE MOVEMENT
        ====================================================== */

        const scene =
            document.querySelector(".orbit-scene");

        if(scene){

            document.addEventListener(
                "mousemove",
                function(e){

                    const x =
                        (e.clientX /
                        window.innerWidth - .5);

                    const y =
                        (e.clientY /
                        window.innerHeight - .5);

                    const rotateY =
                        x * 8;

                    const rotateX =
                        y * -5;

                    scene.style.transform =
                        "rotateX(" +
                        rotateX +
                        "deg) rotateY(" +
                        rotateY +
                        "deg)";

                }
            );

            document.addEventListener(
                "mouseleave",
                function(){

                    scene.style.transform =
                        "rotateX(0deg) rotateY(0deg)";

                }
            );

        }


        /* =====================================================
           CARD 3D TILT
        ====================================================== */

        const cards =
            document.querySelectorAll(".service-card");

        cards.forEach(function(card){

            card.addEventListener(
                "mousemove",
                function(e){

                    const rect =
                        card.getBoundingClientRect();

                    const x =
                        e.clientX - rect.left;

                    const y =
                        e.clientY - rect.top;

                    const centerX =
                        rect.width / 2;

                    const centerY =
                        rect.height / 2;

                    const rotateY =
                        ((x - centerX) / centerX) * 3;

                    const rotateX =
                        ((y - centerY) / centerY) * -3;

                    card.style.transform =
                        "perspective(900px) " +
                        "translateY(-10px) " +
                        "rotateX(" +
                        rotateX +
                        "deg) " +
                        "rotateY(" +
                        rotateY +
                        "deg)";

                }
            );

            card.addEventListener(
                "mouseleave",
                function(){

                    card.style.transform = "";

                }
            );

        });


        /* =====================================================
           PREVENT EXCESSIVE 3D ON TOUCH DEVICES
        ====================================================== */

        if(
            window.matchMedia &&
            window.matchMedia("(pointer: coarse)").matches
        ){

            document.body.classList.add("touch-device");

        }

    </script>

</body>
</html>