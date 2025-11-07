<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlovaChat - {{ isset($flovatar) ? ($flovatar->name ? $flovatar->name : 'Flovatar #'.$flovatar->flow_id) : 'Talk with any Flovatar!' }}</title>

    <meta name="description" content="Talk with any Flovatar about anything!{{ isset($assistant) ? ' - '.$assistant->description : '' }}" />
    <link rel="canonical" href="https://flovatar.com/" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="FlovaChat - {{ isset($flovatar) ? ($flovatar->name ? $flovatar->name : 'Flovatar #'.$flovatar->flow_id) : 'Talk with any Flovatar!' }}" />
    <meta property="og:description" content="Talk with any Flovatar about anything!{{ isset($assistant) ? ' - '.$assistant->description : '' }}" />
    <meta property="og:url" content="https://flovatar.com/" />
    <meta property="og:site_name" content="Flovatar" />
    <meta property="article:modified_time" content="2023-03-08T02:29:49+00:00" />
    <meta property="og:image" content="{{ isset($flovatar) ? 'https://images.flovatar.com/flovatar/png/'.$flovatar->flow_id : 'https://flovatar.com/images/share.png' }}" />
    <meta property="og:image:width" content="{{ isset($flovatar) ? 480 : 1200 }}" />
    <meta property="og:image:height" content="{{ isset($flovatar) ? 480 : 630 }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:image" content="{{ isset($flovatar) ? 'https://images.flovatar.com/flovatar/png/'.$flovatar->flow_id : 'https://flovatar.com/images/share.png' }}" />
    <meta name="twitter:site" content="@flovatar" />


    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <style>
        .message-list{
            max-height: 75vh;
        }
        .flovatar-img{
            max-height: 50vh;
            min-height: 25vh;
        }
        .message {
            padding: 0.5rem 1rem;
            margin: 0.25rem;
            border-radius: 20px;
            max-width: 80%;
        }
        .sent {
            background-color: #e5e5e5;
            align-self: flex-end;
        }
        .received {
            background-color: #007aff;
            color: white;
            align-self: flex-start;
        }
        .dark .message.sent {
            background-color: #4a4a4a;
            color: white;
        }
        .chat-container {
            background-color: white;
        }
        .dark .chat-container {
            background-color: #2a2a2a;
        }

        .earth {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: -1;
            height: 100vh;
        }
        .stars {
            position: absolute;
            left: 0;
            top: 88px;
            z-index: -1;
        }
        .stars.right {
            left: auto;
            right: 0;
        }
        .earth-wrapper {
            display: flex;
            height: 100vh;
            overflow-y: hidden;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            z-index: -1;
        }
        .earth img {
            width: 100%;
            position: absolute;
            bottom: -20%;
        }
        body {
            background-color: #1a083a !important;
            position: relative;
        }
        .chat-container {
            background-color: rgba(255,255,255,0.85);
            margin: 0 !important;
            border: 3px solid black !important;
        }
        .chat-header {
            margin: 20px !important;
            width: calc(100% - 40px);
        }
        .chat-device-frame {
            display: flex;
            border-radius: 40px;
            background-color: #92a09f;
            border: 3px solid black;
            overflow: hidden;
            padding: 10px 56px;
            margin: 0 auto;
            width: 60%;
            min-width: 480px;
            height: calc(100vh - 150px);
            position: relative;
            box-shadow: inset 0 0 10px 10px rgba(0,0,0,.2);
        }
        .chat-controls-left {
            display: flex;
            width: 50px;
            height: 100%;
            position: absolute;
            top: 0;
            left: 3px;
            z-index: 5;
            align-items: center;
        }
        .chat-controls-right {
            display: flex;
            width: 50px;
            height: 100%;
            position: absolute;
            top: 0;
            right: 3px;
            z-index: 5;
            align-items: center;
        }
        .chat-wrapper {
            display: flex;
            justify-content: center;
            width: 100%;
            overflow-x: hidden;
        }

        @media screen and (max-width: 480px) {
            .chat-device-frame {
                width: calc(100vw + 112px);
            }
        }
        .social-share-button {
            position: absolute;
            top: 5px;
            right: 5px;
            height: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
        }
        .social-share-button svg {
            width: 30px;
            height: 30px;
            cursor: pointer;
            margin: 0 5px;
            color: #000;
        }
        .dark .social-share-button svg {
            color: #fff;
        }

        .social-footer{
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: rgba(255,255,255,0.5);
            z-index: 100;
            padding-bottom: 5px;
        }
        .dark .social-footer{
            background-color: rgba(42,42,42,0.7);
        }

        .social-footer img,
        .social-footer svg {
            width: 24px;
            height: 24px;
            cursor: pointer;
            margin: 0 4px;
            color: #000;
        }

        .dark .social-footer svg {
            color: #fff;
        }





        @font-face {
            font-family: 'SequentialistBB';
            src: url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/4273/306FA6_1_0.woff2') format('woff2'),
            url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/4273/306FA6_0_0.woff') format('woff');
            font-style: normal;
            font-weight: 400;
        }
        @font-face {
            font-family: 'SequentialistBB';
            src: url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/4273/306FA6_0_0.woff2') format('woff2'),
            url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/4273/306FA6_0_0.woff') format('woff');
            font-style: italic;
            font-weight: 400;
        }
        blockquote.bubble {
            background-position: center;
            background-repeat: no-repeat !important;
            background-size: 100% 100%;
            margin: 0 auto;
            text-align: center;
            height: 0;
            box-sizing: content-box;
            line-height: 1;
            font-family: SequentialistBB, cursive;
            font-size: 1.5vw;
        }
        blockquote.speech {
            background: url(https://s3-us-west-2.amazonaws.com/s.cdpn.io/4273/speech-bubble.svg);
            width: 25%;
            padding-top: 6%;
            padding-bottom: 20%;
        }
        blockquote.whisper {
            background: url(https://s3-us-west-2.amazonaws.com/s.cdpn.io/4273/whisper.svg);
            width: 25%;
            font-size: 2vw;
            color: #ccc;
            font-style: italic;
            padding: 6% 5% 15%;
        }
        blockquote.electric {
            background: url(https://s3-us-west-2.amazonaws.com/s.cdpn.io/4273/electric.svg);
            width: 25%;
            font-size: 2.4vw;
            font-style: italic;
            padding: 4% 6% 12% 0%;
        }
        blockquote.electric span {
            display: block;
            font-size: 3vw;
            font-weight: bold;
        }

        .flovatar-chat-item{
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 0 20px 0;
        }
        .flovatar-chat-item img {
            margin-top: -50px;
            width: 250px;
            height: 250px;
        }

    </style>


    <!-- Scripts -->
    <script>
        var svgOpen = '{!! config('flovatar.svgOpen') !!}';
        var svgClose = '{!! config('flovatar.svgClose') !!}';
    </script>
    <script src="{{ mix('js/app.js') }}" defer></script>
    <script defer data-domain="flovatar.com" src="https://plausible.io/js/plausible.js"></script>

    <script>
        @if(isset($flovatar))
            window.flovatar = @json($flovatar);
        @endif

        @if(isset($templates))
            window.templates = @json($templates);
        @endif

        @if(isset($assistant))
            window.assistant = @json($assistant);
        @endif

        @if(isset($assistants))
            window.assistants = @json($assistants);
        @endif

        @if(isset($voices))
            window.voices = @json($voices);
        @endif

    </script>
    <script src="https://cdn.jsdelivr.net/npm/sharer.js@latest/sharer.min.js"></script>
</head>
<body class="bg-gray-100 dark:bg-gray-800 transition-colors duration-300">

    <div class="earth-wrapper">
        <div class="stars left">
            <img src="/images/stars-l.png" width="307" alt="">
        </div>
        <div class="stars right">
            <img src="/images/stars-r.png" width="364" alt="">
        </div>
        <div class="earth">
            <img src="/images/earth-cresent.png" alt="">
        </div>
    </div>

    <div
        x-data="appData"
        x-init="initApp($dispatch); jsLoaded = true;"
        @user-check-finish.window="userCheckLoading = false"
        @user-loaded.window="user = $event.detail.user"
        @profile-loaded.window="profile = $event.detail.profile; userCheckLoading = false;"
        @logged-out.window="user = null; profile = null; currentWallet = null;"
        @loading-start.window="loading = true; waitingSeal = false;"
        @waiting-seal.window="loading = false; waitingSeal = true;"
        @loading-completed.window="loading = false; waitingSeal = false; sealCompleted = true; setTimeout(()=>sealCompleted=false,3000);"
        @transaction-error.window="handleTransactionErrorMessage($event.detail.message)"
        @address-found.window="handleAddressFound($event.detail.profile)"
        @login-failed.window="showLoginResult = true"
    >


        <div
            class="flex flex-col h-screen"
            x-data="chatData(window.flovatar, window.templates, window.assistant, window.voices)"
            x-init="initChat($dispatch);"
            @flovatar-loaded.window="loadFlovatarFromChain($event.detail.flovatar)"
            @flovatar-sale-loaded.window="loadSaleFromChain($event.detail.sale)"
        >

            <div class="chat-header flex justify-between items-center">
                <div class="logo text-pink-500 text-2xl font-bold h-8">
                    <a href="/"><img class="w-auto h-full" src="/images/flovatar-logo-pink.svg" alt="Flovatar"></a>
                </div>
                <label for="dark-mode-toggle" class="flex items-center cursor-pointer" @click.prevent.default="toggleDarkMode()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#666" class="w-6 h-6 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                    </svg>

                    <button type="button" x-bind:class="darkMode ? 'bg-indigo-600' : 'bg-gray-200'" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2" role="switch" aria-checked="false">
                        <span class="sr-only">Dark Mode</span>
                        <span aria-hidden="true" x-bind:class="darkMode ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#666" class="w-6 h-6 ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                </label>
            </div>

            <div class="chat-wrapper" @click="showSocial = false">
                <div class="chat-device-frame">
                    <div class="chat-controls-left">
                        <img src="/images/controls-left.svg" alt="" />
                    </div>
